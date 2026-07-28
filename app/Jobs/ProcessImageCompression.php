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
use Intervention\Image\ImageManagerStatic as Image;

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

        try {
            $img = Image::make($inputPath);

            if ($img->width() > 1470) {
                $img->resize(1470, null, fn($c) => $c->aspectRatio()->upsize());
            }

            $img->save($inputPath, 85);
        } catch (\Exception $e) {
            Log::error('ProcessImageCompression: failed', [
                'file'  => $this->filePath,
                'error' => $e->getMessage(),
            ]);
            return;
        }

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
