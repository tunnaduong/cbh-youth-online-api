<?php

namespace App\Jobs;

use App\Models\UserContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessImageCompression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        private string $filePath,
        private ?int $contentId = null,
    ) {}

    public function handle(): void
    {
        if ($this->contentId) {
            UserContent::where('id', $this->contentId)->update(['photo_status' => 'processing']);
        }

        $disk = Storage::disk('public');
        $inputPath = $disk->path($this->filePath);

        if (!file_exists($inputPath)) {
            Log::error("ProcessImageCompression: input file not found: {$inputPath}");
            if ($this->contentId) {
                UserContent::where('id', $this->contentId)->update(['photo_status' => 'failed']);
            }
            return;
        }

        $tmpPath = $inputPath . '.tmp';

        // Check if the image width is already under 1470px
        $imageInfo = @getimagesize($inputPath);
        if ($imageInfo) {
            $width = $imageInfo[0];
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($inputPath);
                if (!empty($exif['Orientation']) && in_array($exif['Orientation'], [5, 6, 7, 8])) {
                    $width = $imageInfo[1]; // Swap width and height for rotated images
                }
            }

            if ($width <= 1470) {
                if ($this->contentId) {
                    UserContent::where('id', $this->contentId)->update([
                        'file_size'    => filesize($inputPath),
                        'photo_status' => 'completed',
                    ]);
                }
                Log::info('ProcessImageCompression: skipped because width is <= 1470px', [
                    'file'  => $this->filePath,
                    'width' => $width,
                ]);
                return;
            }
        }

        // Auto-orient to correct EXIF rotation, resize to max 1470px width, and compress
        $cmd = sprintf(
            'convert %s -auto-orient -resize "1470x>" -quality 85 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($tmpPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tmpPath)) {
            Log::error('ProcessImageCompression: imagemagick failed', [
                'file'   => $this->filePath,
                'output' => implode("\n", $output),
            ]);
            @unlink($tmpPath);
            if ($this->contentId) {
                UserContent::where('id', $this->contentId)->update(['photo_status' => 'failed']);
            }
            return;
        }

        rename($tmpPath, $inputPath);

        $newSize = filesize($inputPath);

        if ($this->contentId) {
            UserContent::where('id', $this->contentId)->update([
                'file_size'    => $newSize,
                'photo_status' => 'completed',
            ]);
        }

        Log::info('ProcessImageCompression: completed', [
            'file' => $this->filePath,
            'size' => $newSize,
        ]);
    }
}
