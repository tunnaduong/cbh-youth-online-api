<?php

namespace App\Services;

use App\Mail\ContentApprovedMail;
use App\Models\ModerationQueue;
use App\Models\Topic;
use App\Models\TopicComment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * AI-powered content moderation for forum topics and comments.
 *
 * Verdicts:
 *  - 'approved'     → publish immediately
 *  - 'rejected'     → block and return error to user
 *  - 'needs_review' → publish as pending (hidden until a human approves)
 */
class ContentModerationService
{
    private const API_URL = 'https://chat-api.chuyenbienhoa.com/v1/chat/completions';
    private const MODEL = 'gemini-flash-lite';
    private const API_KEY = 'sk-ilovecyo';

    /** Shown in the queue when media (not the text) is what needs a human. */
    private const ATTACHMENT_REVIEW_REASON = 'Có ảnh/video/tệp đính kèm - AI không đọc được, cần người kiểm duyệt xem.';

    private const SYSTEM_PROMPT = <<<PROMPT
Bạn là hệ thống kiểm duyệt nội dung tự động của Chuyên Biên Hòa Youth Online (CYO) - cộng đồng học sinh THPT Chuyên Biên Hòa.

Nhiệm vụ: Đánh giá nội dung bài đăng/bình luận và trả về JSON quyết định kiểm duyệt.

Đây là cộng đồng của học sinh THPT (phần lớn dưới 18 tuổi). TUYỆT ĐỐI KHÔNG cho phép nội dung 18+/NSFW.

Tiêu chuẩn cộng đồng:
- TỪ CHỐI (rejected) - nội dung 18+/NSFW, áp dụng nghiêm ngặt nhất:
  * Nội dung khiêu dâm, mô tả hành vi tình dục, truyện/thơ/tâm sự có yếu tố tình dục lộ liễu.
  * Khỏa thân, bán khỏa thân, mô tả hoặc yêu cầu ảnh nhạy cảm ("nude", "ảnh hở", "check hàng"...).
  * Liên kết, tên trang web, nhóm chat hoặc từ khóa dẫn tới nội dung người lớn; rao bán/chia sẻ/xin nội dung khiêu dâm.
  * Gạ gẫm, mời gọi quan hệ tình dục, quấy rối tình dục người khác.
  * Mọi nội dung tự gắn nhãn "18+", "NSFW", "chỉ dành cho người lớn".
- TỪ CHỐI (rejected) - các vi phạm khác: ngôn từ thù địch, phân biệt chủng tộc/giới tính/tôn giáo rõ ràng; spam quảng cáo thương mại không liên quan; kêu gọi bạo lực; thông tin cá nhân nhạy cảm của người khác; lừa đảo; nội dung hoàn toàn vô nghĩa/ký tự rác.
- CẦN XEM XÉT (needs_review): Nội dung có thể vi phạm nhưng cần ngữ cảnh để xác định (tranh luận nhạy cảm, chính trị, tôn giáo, sức khỏe tâm thần, nội dung buồn/tiêu cực nhưng không rõ ràng vi phạm); ám chỉ/đùa cợt mang màu sắc tình dục nhưng chưa lộ liễu; nội dung có liên kết ngoài đáng ngờ; nội dung có thể là spam nhưng không chắc.
- CHẤP THUẬN (approved): Mọi nội dung hợp lệ khác, kể cả thảo luận học tập, chia sẻ kinh nghiệm, góp ý, hỏi đáp, tin tức, sáng tác, tâm sự bình thường, humor lành mạnh. Giáo dục giới tính/sức khỏe sinh sản nghiêm túc, đúng mực vẫn được chấp thuận - điều bị cấm là nội dung khiêu dâm, không phải kiến thức.

Lưu ý: Hãy bao dung với học sinh - ngôn ngữ teen, tiếng lóng thông thường, cách viết không chính thống là bình thường. Chỉ từ chối khi vi phạm rõ ràng và nghiêm trọng. NGOẠI LỆ: sự bao dung này KHÔNG áp dụng cho nội dung 18+/NSFW - với nhóm này hãy từ chối ngay cả khi chỉ ở mức vừa phải.

Trả về JSON theo đúng format sau (không có markdown, không có text khác):
{"verdict":"approved|rejected|needs_review","reason":"lý do ngắn gọn bằng tiếng Việt, tối đa 100 ký tự"}
PROMPT;

    /**
     * Moderate a topic (title + body). Returns moderation result.
     *
     * @return array{verdict: string, reason: string}
     */
    public function moderateTopic(string $title, string $body): array
    {
        $content = "Tiêu đề: {$title}\n\nNội dung: {$body}";
        return $this->callApi($content);
    }

    /**
     * Moderate a comment body. Returns moderation result.
     *
     * @return array{verdict: string, reason: string}
     */
    public function moderateComment(string $body): array
    {
        $content = "Bình luận: {$body}";
        return $this->callApi($content);
    }

    /**
     * Apply moderation result to a topic: set moderation_status, create queue entry if needed.
     * Returns ['action' => 'approved'|'pending'|'rejected', 'message' => string|null]
     */
    public function applyToTopic(Topic $topic, array $result): array
    {
        $verdict = $result['verdict'];
        $reason = $result['reason'] ?? '';

        // The model only ever saw the title and body - it cannot look at an
        // attached image, video or document. Never let it clear a post whose
        // attachments nobody has reviewed: a clean caption over an abusive
        // image would sail straight through. A rejection stands, since the
        // text alone was already enough to refuse it.
        if ($verdict === 'approved' && $topic->hasAttachments()) {
            $verdict = 'needs_review';
            $reason = self::ATTACHMENT_REVIEW_REASON;
        }

        $snapshot = json_encode([
            'title' => $topic->title,
            'body' => mb_substr($topic->description ?? '', 0, 2000),
            // Remember the author's own visibility choice: holding a post for
            // review forces hidden=true, so approving it must restore this
            // rather than blindly publishing a post they wanted hidden.
            'original_hidden' => (bool) $topic->hidden,
        ]);

        if ($verdict === 'approved') {
            $topic->update(['moderation_status' => 'approved']);
            self::sendApprovedEmail($topic, 'topic');
            return ['action' => 'approved', 'message' => null];
        }

        if ($verdict === 'rejected') {
            $topic->update(['moderation_status' => 'rejected', 'hidden' => true]);
            ModerationQueue::create([
                'content_type' => 'topic',
                'content_id' => $topic->id,
                'user_id' => $topic->user_id,
                'content_snapshot' => $snapshot,
                'status' => 'rejected',
                'ai_verdict' => 'rejected',
                'ai_reason' => $reason,
            ]);
            return ['action' => 'rejected', 'message' => $reason ?: 'Nội dung không đạt tiêu chuẩn cộng đồng.'];
        }

        // needs_review
        $topic->update(['moderation_status' => 'pending', 'hidden' => true]);
        ModerationQueue::create([
            'content_type' => 'topic',
            'content_id' => $topic->id,
            'user_id' => $topic->user_id,
            'content_snapshot' => $snapshot,
            'status' => 'pending',
            'ai_verdict' => 'needs_review',
            'ai_reason' => $reason,
        ]);
        return ['action' => 'pending', 'message' => 'Bài viết của bạn đang chờ kiểm duyệt và sẽ được duyệt sớm.'];
    }

    /**
     * Apply moderation result to a comment. Returns ['action', 'message'].
     */
    public function applyToComment(TopicComment $comment, array $result): array
    {
        $verdict = $result['verdict'];
        $reason = $result['reason'] ?? '';

        // Same rule as applyToTopic: the model never saw these images.
        if ($verdict === 'approved' && !empty($comment->image_urls)) {
            $verdict = 'needs_review';
            $reason = self::ATTACHMENT_REVIEW_REASON;
        }

        $snapshot = json_encode([
            'topic_id' => $comment->topic_id,
            'body' => mb_substr($comment->comment ?? '', 0, 2000),
        ]);

        if ($verdict === 'approved') {
            $comment->update(['moderation_status' => 'approved']);
            self::sendApprovedEmail($comment, 'comment');
            return ['action' => 'approved', 'message' => null];
        }

        if ($verdict === 'rejected') {
            $comment->update(['moderation_status' => 'rejected']);
            ModerationQueue::create([
                'content_type' => 'comment',
                'content_id' => $comment->id,
                'user_id' => $comment->user_id,
                'content_snapshot' => $snapshot,
                'status' => 'rejected',
                'ai_verdict' => 'rejected',
                'ai_reason' => $reason,
            ]);
            return ['action' => 'rejected', 'message' => $reason ?: 'Bình luận không đạt tiêu chuẩn cộng đồng.'];
        }

        // needs_review
        $comment->update(['moderation_status' => 'pending']);
        ModerationQueue::create([
            'content_type' => 'comment',
            'content_id' => $comment->id,
            'user_id' => $comment->user_id,
            'content_snapshot' => $snapshot,
            'status' => 'pending',
            'ai_verdict' => 'needs_review',
            'ai_reason' => $reason,
        ]);
        return ['action' => 'pending', 'message' => 'Bình luận của bạn đang chờ kiểm duyệt.'];
    }

    /**
     * Email the author that their topic/comment was approved and is now public.
     * Used both by AI auto-approval here and by the human admin approve flow
     * (ModerationController::approve()).
     *
     * @param  Topic|TopicComment  $content
     */
    public static function sendApprovedEmail($content, string $type): void
    {
        $user = $content->user;
        $topic = $type === 'comment' ? $content->topic : $content;

        if (!$user || !$user->email || !$topic) {
            return;
        }

        $baseUrl = rtrim(config('app.ui_url', env('APP_UI_URL', 'http://localhost:3000')), '/');

        try {
            Mail::to($user->email)->queue(new ContentApprovedMail($user, $type, $topic->title, $baseUrl . $topic->getUrl()));
        } catch (\Throwable $e) {
            Log::error('Failed to send content approved email', [
                'content_type' => $type,
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function callApi(string $userContent): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::API_KEY,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'max_tokens' => 150,
                    'temperature' => 0,
                ]);

            if (!$response->successful()) {
                Log::warning('ContentModerationService: API error', ['status' => $response->status()]);
                return $this->fallback($userContent);
            }

            $text = trim($response->json('choices.0.message.content') ?? '');
            $decoded = json_decode($text, true);

            if (!isset($decoded['verdict']) || !in_array($decoded['verdict'], ['approved', 'rejected', 'needs_review'])) {
                Log::warning('ContentModerationService: unexpected response', ['text' => $text]);
                return $this->fallback($userContent);
            }

            return [
                'verdict' => $decoded['verdict'],
                'reason' => $decoded['reason'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('ContentModerationService: exception', ['error' => $e->getMessage()]);
            return $this->fallback($userContent);
        }
    }

    /**
     * Blatant 18+/NSFW markers, checked only when the AI is unreachable.
     *
     * Deliberately short and unambiguous: this is a safety net for an outage,
     * not a general profanity filter (the model handles nuance when it's up).
     * Anything matched goes to a human rather than being rejected outright, so
     * a false positive costs one review instead of wrongly blocking a post.
     */
    private const NSFW_FALLBACK_TERMS = [
        'khiêu dâm', 'khoả thân', 'khỏa thân', 'ảnh nóng', 'lộ hàng', 'check hàng',
        'gạ tình', 'phim sex', 'clip sex', 'sex chat', 'porn', 'pornhub', 'xvideos',
        'onlyfans', 'hentai', 'nsfw', 'nude',
    ];

    /**
     * Used when the AI can't be reached. Still fails open so an outage never
     * blocks the forum - except for content carrying an unmistakable 18+
     * marker, which is held for review rather than auto-published. Without
     * this, every NSFW post would sail through during an AI outage.
     */
    private function fallback(string $userContent = ''): array
    {
        $haystack = mb_strtolower($userContent);

        foreach (self::NSFW_FALLBACK_TERMS as $term) {
            if (str_contains($haystack, $term)) {
                return [
                    'verdict' => 'needs_review',
                    'reason' => 'Nghi ngờ nội dung 18+ (AI kiểm duyệt tạm thời không khả dụng).',
                ];
            }
        }

        // "18+" on its own, but not inside a sum like "18+5" - this is a
        // school forum, maths posts shouldn't trip the filter.
        if (preg_match('/\b18\s*\+(?!\s*\d)/u', $haystack)) {
            return [
                'verdict' => 'needs_review',
                'reason' => 'Nghi ngờ nội dung 18+ (AI kiểm duyệt tạm thời không khả dụng).',
            ];
        }

        return ['verdict' => 'approved', 'reason' => ''];
    }
}
