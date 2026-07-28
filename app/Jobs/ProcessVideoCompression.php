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

class ProcessVideoCompression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /**
     * @param string   $filePath   Relative path on the public disk
     * @param int|null $contentId  UserContent ID to update video_status/file_size (optional)
     */
    public function __construct(
        private string $filePath,
        private ?int $contentId = null,
    ) {}

    public function handle(): void
    {
        if ($this->contentId) {
            UserContent::where('id', $this->contentId)->update(['video_status' => 'processing']);
        }

        $disk = Storage::disk('public');
        $inputPath = $disk->path($this->filePath);

        if (!file_exists($inputPath)) {
            Log::error("ProcessVideoCompression: input file not found: {$inputPath}");
            if ($this->contentId) {
                UserContent::where('id', $this->contentId)->update(['video_status' => 'failed']);
            }
            return;
        }

        $tmpPath = $inputPath . '.tmp.mp4';

        $fps = $this->probeFrameRate($inputPath);
        $fpsFilter = $fps > 30 ? ',fps=fps=30' : '';

        $scaleFilter = "scale='if(gt(iw\\,1920)\\,1920\\,iw)':'if(gt(ih\\,1080)\\,1080\\,ih)':force_original_aspect_ratio=decrease:flags=lanczos{$fpsFilter}";

        $cmd = sprintf(
            'ffmpeg -y -i %s -c:v libx265 -b:v 4700k -maxrate 4700k -bufsize 9400k -vf %s -c:a aac -b:a 128k -tag:v hvc1 -movflags +faststart %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($scaleFilter),
            escapeshellarg($tmpPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tmpPath)) {
            Log::error('ProcessVideoCompression: ffmpeg failed', [
                'file' => $this->filePath,
                'output' => implode("\n", array_slice($output, -20)),
            ]);
            @unlink($tmpPath);
            if ($this->contentId) {
                UserContent::where('id', $this->contentId)->update(['video_status' => 'failed']);
            }
            return;
        }

        // Overwrite original with compressed file (keeps same path/name)
        rename($tmpPath, $inputPath);

        $newSize = filesize($inputPath);

        if ($this->contentId) {
            UserContent::where('id', $this->contentId)->update([
                'file_size'    => $newSize,
                'file_type'    => 'video/mp4',
                'video_status' => 'completed',
            ]);
        }

        Log::info('ProcessVideoCompression: completed', [
            'file' => $this->filePath,
            'size' => $newSize,
        ]);
    }

    private function probeFrameRate(string $filePath): float
    {
        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 -show_entries stream=r_frame_rate -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        $output = trim(shell_exec($cmd) ?? '');

        if (str_contains($output, '/')) {
            [$num, $den] = explode('/', $output);
            $den = (int) $den;
            return $den > 0 ? (float) $num / $den : 30.0;
        }

        return (float) $output ?: 30.0;
    }
}
