<?php

namespace App\Console\Commands;

use App\Models\UserContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * One-off backfill for avatars uploaded before updateAvatar() started
 * cropping to 156x156 and re-encoding as JPEG (see UserController) - those
 * older avatars are still sitting on disk at whatever size/format they were
 * originally uploaded at, some several MB. Re-processes each one in place
 * with the exact same crop/resize/encode as a fresh upload.
 */
class CompressExistingAvatars extends Command
{
    protected $signature = 'avatars:compress-existing {--force : Recompress even avatars already at 156x156 or smaller}';
    protected $description = 'Crop/resize all existing avatars to 156x156 JPEG to match current uploads';

    private const TARGET_SIZE = 156;
    private const QUALITY = 82;

    public function handle(): int
    {
        $avatars = UserContent::where('file_path', 'like', 'avatars/%')->get();

        if ($avatars->isEmpty()) {
            $this->info('No avatars found.');
            return self::SUCCESS;
        }

        $this->info("Found {$avatars->count()} avatar(s).");
        $bar = $this->output->createProgressBar($avatars->count());
        $bar->start();

        $disk = Storage::disk('public');
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($avatars as $content) {
            $bar->advance();

            $path = $disk->path($content->file_path);
            if (!file_exists($path)) {
                $failed++;
                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->warn("Missing file, skipping: {$content->file_path}");
                }
                continue;
            }

            $sizeBefore = filesize($path);

            if (!$this->option('force')) {
                $dimensions = @getimagesize($path);
                if ($dimensions && $dimensions[0] <= self::TARGET_SIZE && $dimensions[1] <= self::TARGET_SIZE) {
                    $skipped++;
                    continue;
                }
            }

            try {
                $image = Image::make($path);
                $square = min($image->width(), $image->height());
                $image->crop($square, $square)->resize(self::TARGET_SIZE, self::TARGET_SIZE);
                $encoded = (string) $image->encode('jpg', self::QUALITY);
            } catch (\Throwable $e) {
                $failed++;
                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->warn("Failed to process {$content->file_path}: {$e->getMessage()}");
                }
                continue;
            }

            // Always ends up JPEG regardless of the original extension/mime -
            // normalize the stored filename/path/type to match, same as
            // UserController::updateAvatar() does for new uploads.
            $newFileName = preg_replace('/\.\w+$/', '', $content->file_name) . '.jpg';
            $newFilePath = 'avatars/' . $newFileName;

            $disk->put($newFilePath, $encoded);
            if ($newFilePath !== $content->file_path) {
                $disk->delete($content->file_path);
            }

            $content->update([
                'file_name' => $newFileName,
                'file_path' => $newFilePath,
                'file_type' => 'image/jpeg',
                'file_size' => $disk->size($newFilePath),
            ]);

            $processed++;
            $bytesBefore += $sizeBefore;
            $bytesAfter += $disk->size($newFilePath);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Compressed: {$processed}, skipped (already small): {$skipped}, failed: {$failed}");
        if ($processed > 0) {
            $this->info(sprintf(
                'Size before: %s, after: %s (saved %s)',
                $this->formatFileSize($bytesBefore),
                $this->formatFileSize($bytesAfter),
                $this->formatFileSize($bytesBefore - $bytesAfter)
            ));
        }

        return self::SUCCESS;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return sprintf('%.2f %s', $bytes, $units[$index]);
    }
}
