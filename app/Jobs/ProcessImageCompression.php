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

    /**
     * @param string   $filePath   Relative path on the public disk
     * @param int|null $contentId  UserContent ID to update file_size (optional)
     */
    public function __construct(
        private string $filePath,
        private ?int $contentId = null,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');
        $inputPath = $disk->path($this->filePath);

        if (!file_exists($inputPath)) {
            Log::error("ProcessImageCompression: input file not found: {$inputPath}");
            return;
        }

        $tmpPath = $inputPath . '.tmp';

        // Resize to max 1470px width (only if wider), keep aspect ratio, in-place overwrite
        $cmd = sprintf(
            'convert %s -resize %s\> -strip %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg('1470x'),
            escapeshellarg($tmpPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tmpPath)) {
            Log::error('ProcessImageCompression: convert failed', [
                'file'   => $this->filePath,
                'output' => implode("\n", $output),
            ]);
            @unlink($tmpPath);
            return;
        }

        rename($tmpPath, $inputPath);

        $newSize = filesize($inputPath);

        if ($this->contentId) {
            UserContent::where('id', $this->contentId)->update(['file_size' => $newSize]);
        }

        Log::info('ProcessImageCompression: completed', [
            'file' => $this->filePath,
            'size' => $newSize,
        ]);
    }
}
