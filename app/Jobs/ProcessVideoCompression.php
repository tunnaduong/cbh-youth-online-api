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

    public function __construct(
        private int $contentId,
        private string $filePath,
    ) {}

    public function handle(): void
    {
        $content = UserContent::find($this->contentId);
        if (!$content) {
            return;
        }

        $content->update(['video_status' => 'processing']);

        $inputPath = Storage::disk('public')->path($this->filePath);

        if (!file_exists($inputPath)) {
            Log::error("ProcessVideoCompression: input file not found: {$inputPath}");
            $content->update(['video_status' => 'failed']);
            return;
        }

        $outputFileName = pathinfo($this->filePath, PATHINFO_FILENAME) . '_h265.mp4';
        $outputRelativePath = 'videos/' . $outputFileName;
        $outputPath = Storage::disk('public')->path($outputRelativePath);

        $fps = $this->probeFrameRate($inputPath);
        $fpsFilter = $fps > 30 ? ',fps=fps=30' : '';

        // Scale to max 1080p keeping aspect ratio, encode H.265 at 4.7Mbps
        $scaleFilter = "scale='if(gt(iw\\,1920)\\,1920\\,iw)':'if(gt(ih\\,1080)\\,1080\\,ih)':force_original_aspect_ratio=decrease:flags=lanczos{$fpsFilter}";

        $cmd = sprintf(
            'ffmpeg -y -i %s -c:v libx265 -b:v 4700k -maxrate 4700k -bufsize 9400k -vf %s -c:a aac -b:a 128k -tag:v hvc1 -movflags +faststart %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($scaleFilter),
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error("ProcessVideoCompression: ffmpeg failed for content #{$this->contentId}", [
                'output' => implode("\n", array_slice($output, -20)),
            ]);
            $content->update(['video_status' => 'failed']);
            return;
        }

        // Replace original with compressed file
        Storage::disk('public')->delete($this->filePath);

        $newSize = filesize($outputPath);

        $content->update([
            'file_name'    => $outputFileName,
            'file_path'    => $outputRelativePath,
            'file_type'    => 'video/mp4',
            'file_size'    => $newSize,
            'video_status' => 'completed',
        ]);

        Log::info("ProcessVideoCompression: completed for content #{$this->contentId}, size reduced to {$newSize} bytes");
    }

    private function probeFrameRate(string $filePath): float
    {
        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 -show_entries stream=r_frame_rate -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        $output = trim(shell_exec($cmd) ?? '');

        // r_frame_rate is returned as fraction e.g. "60000/1001"
        if (str_contains($output, '/')) {
            [$num, $den] = explode('/', $output);
            $den = (int) $den;
            return $den > 0 ? (float) $num / $den : 30.0;
        }

        return (float) $output ?: 30.0;
    }
}
