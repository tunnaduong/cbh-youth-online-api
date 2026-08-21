<?php

namespace App\Jobs;

use App\Models\UserContent;
use App\Services\MediaThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * UserContent (posts/stories) counterpart to ProcessVideoPreview - same
 * small muted low-bitrate clip for feed/card autoplay, just written to a
 * plain column (preview_path) instead of a JSON metadata field since
 * UserContent isn't a chat message.
 */
class ProcessUserContentVideoPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private string $filePath,
        private int $userContentId,
    ) {}

    public function handle(): void
    {
        $previewPath = MediaThumbnailService::videoPreview($this->filePath, 'thumbnails/video-previews');
        if (!$previewPath) {
            return;
        }

        $content = UserContent::find($this->userContentId);
        if (!$content) {
            Storage::disk('public')->delete($previewPath);
            return;
        }

        $content->update(['preview_path' => $previewPath]);

        Log::info('ProcessUserContentVideoPreview: completed', [
            'file' => $this->filePath,
            'user_content_id' => $this->userContentId,
        ]);
    }
}
