<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\MediaThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a small, muted, low-bitrate copy of a chat video for inline
 * autoplay (see MediaThumbnailService::videoPreview) and attaches it to the
 * message's metadata once ready - decode cost scales with the *source*
 * resolution regardless of how small it's displayed, so autoplaying the
 * full ProcessVideoCompression output (up to 1920x1080 @ 4700kbps) inline
 * in a ~200px bubble burns far more CPU than that tiny player needs.
 * Dispatched independently of ProcessVideoCompression (not chained) -
 * both just transcode from whatever bytes exist at the path when they run,
 * so there's no ordering requirement.
 */
class ProcessVideoPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private string $filePath,
        private int $messageId,
    ) {}

    public function handle(): void
    {
        $previewPath = MediaThumbnailService::videoPreview($this->filePath, 'chat_files/video-previews');
        if (!$previewPath) {
            return;
        }

        $message = Message::find($this->messageId);
        if (!$message) {
            // Message was deleted/recalled before the job ran - clean up the
            // now-orphaned preview instead of leaving it on disk forever.
            Storage::disk('public')->delete($previewPath);
            return;
        }

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                // Absolute, matching thumbnail_url's shape (set at message
                // creation time in ChatController) - metadata is returned to
                // clients as-is, with no URL transformation on read.
                'preview_url' => MediaThumbnailService::absoluteUrl($previewPath),
            ]),
        ]);

        Log::info('ProcessVideoPreview: completed', ['file' => $this->filePath, 'message_id' => $this->messageId]);
    }
}
