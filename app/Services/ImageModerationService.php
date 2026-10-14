<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * AI check for the photos on forum posts and comments.
 *
 * Only a photo the vision model clearly finds fine is published on its own.
 * Everything else - a doubtful photo, a format the model cannot read, a file
 * too large to send, or a model that is down or misconfigured - is held for
 * an admin. A photo is never published unseen.
 */
class ImageModerationService
{
    /** Formats the vision model reads. GIF and anything else go to a human. */
    private const SUPPORTED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** Photos are compressed on upload, so anything this big is not a normal one. */
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 25;

    private const REVIEW_REASON = 'Có ảnh đính kèm - AI chưa kiểm tra được, cần người kiểm duyệt xem.';

    private const SYSTEM_PROMPT = <<<PROMPT
Bạn là hệ thống kiểm duyệt ẢNH tự động của Chuyên Biên Hòa Youth Online (CYO) - cộng đồng học sinh THPT Chuyên Biên Hòa, phần lớn dưới 18 tuổi.

Nhiệm vụ: xem ảnh đính kèm bài đăng/bình luận và quyết định ảnh có được đăng ngay hay không.

Chỉ trả về "approved" khi ảnh RÕ RÀNG hợp lệ: ảnh đời sống, học tập, sự kiện, phong cảnh, đồ vật, ảnh chụp màn hình thông thường, ảnh meme lành mạnh.

Trả về "needs_review" (chuyển cho người kiểm duyệt) khi ảnh có:
- Khỏa thân, bán khỏa thân, ảnh gợi dục, tư thế hoặc trang phục hở hang nhạy cảm; nội dung 18+ hoặc quảng cáo dẫn tới nội dung người lớn.
- Bạo lực, máu me, xác chết, cảnh tai nạn kinh hoàng, hành hạ người hoặc động vật.
- Chống phá Đảng, Nhà nước; xuyên tạc lịch sử; tuyên truyền thù địch; kêu gọi biểu tình, bạo loạn; cờ, biểu tượng, bản đồ mang nội dung chính trị phản động.
- Thù ghét, xúc phạm, phân biệt chủng tộc, giới tính, tôn giáo; bôi nhọ, nhục mạ người khác.
- Lừa đảo, cờ bạc, cá độ, mã QR hoặc tài khoản ngân hàng đáng ngờ, giấy tờ tùy thân hay thông tin cá nhân nhạy cảm của người khác.
- Ảnh quá mờ để nhận ra nội dung, hoặc bạn không chắc chắn.

Khi không chắc chắn, chọn needs_review.

Trả về JSON theo đúng format sau (không có markdown, không có text khác):
{"verdict":"approved|needs_review","reason":"lý do ngắn gọn bằng tiếng Việt, tối đa 100 ký tự"}
PROMPT;

    /**
     * Review photos stored on the public disk.
     *
     * @param  array<int, string>  $paths  Paths as stored in cyo_cdn_user_content.file_path or in a comment's image_urls.
     * @return string|null  null when every photo is clearly fine; otherwise the reason to hold the content for a human.
     */
    public function reviewPaths(array $paths): ?string
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
        if ($paths === []) {
            return null;
        }

        $config = config('services.image_moderation');
        if (empty($config['key'])) {
            Log::error('ImageModerationService: IMAGE_MODERATION_API_KEY is not configured - photos go to review.');
            return self::REVIEW_REASON;
        }

        $dataUris = [];
        foreach ($paths as $path) {
            $dataUri = $this->toDataUri($path);
            if ($dataUri === null) {
                // Not something the model can look at: a person has to.
                return self::REVIEW_REASON;
            }
            $dataUris[] = $dataUri;
        }

        $responses = Http::pool(function (Pool $pool) use ($config, $dataUris) {
            foreach ($dataUris as $index => $dataUri) {
                $pool->as((string) $index)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $config['key'],
                        'Content-Type' => 'application/json',
                    ])
                    ->post($config['url'], [
                        'model' => $config['model'],
                        'messages' => [
                            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                            [
                                'role' => 'user',
                                'content' => [
                                    ['type' => 'text', 'text' => 'Kiểm duyệt ảnh này:'],
                                    ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                                ],
                            ],
                        ],
                        'max_tokens' => 200,
                        'temperature' => 0,
                    ]);
            }
        });

        $reason = null;
        foreach ($responses as $response) {
            $photoReason = $this->reasonFor($response);
            if ($photoReason !== null) {
                // One photo that is not clearly fine is enough to hold the post.
                $reason ??= $photoReason;
            }
        }

        return $reason;
    }

    /**
     * Null when the model approved this photo, otherwise why it must be reviewed.
     * Anything that is not a clear "approved" - including a failed call - counts as not approved.
     *
     * @param  Response|ConnectionException|mixed  $response
     */
    private function reasonFor($response): ?string
    {
        if (! $response instanceof Response) {
            Log::warning('ImageModerationService: request failed', [
                'error' => $response instanceof \Throwable ? $response->getMessage() : null,
            ]);
            return self::REVIEW_REASON;
        }

        if (! $response->successful()) {
            Log::warning('ImageModerationService: API error', ['status' => $response->status()]);
            return self::REVIEW_REASON;
        }

        $text = trim((string) $response->json('choices.0.message.content'));
        // Some models wrap JSON in a code fence even when told not to.
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $text) ?? '';
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! array_key_exists('verdict', $decoded)) {
            Log::warning('ImageModerationService: unexpected response', ['text' => $text]);
            return self::REVIEW_REASON;
        }

        if ($decoded['verdict'] === 'approved') {
            return null;
        }

        $reason = trim((string) ($decoded['reason'] ?? ''));

        return $reason !== '' ? mb_substr($reason, 0, 100) : self::REVIEW_REASON;
    }

    /**
     * The photo as a data URI, or null when it cannot be sent to the model.
     */
    private function toDataUri(string $path): ?string
    {
        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($path) || $disk->size($path) > self::MAX_BYTES) {
                Log::warning('ImageModerationService: photo missing or too large', ['path' => $path]);
                return null;
            }

            $mime = $disk->mimeType($path);
            if (! in_array($mime, self::SUPPORTED_MIME, true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
        } catch (\Throwable $e) {
            Log::error('ImageModerationService: could not read photo', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
