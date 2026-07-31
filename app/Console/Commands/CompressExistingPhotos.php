<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageCompression;
use App\Models\UserContent;
use Illuminate\Console\Command;

class CompressExistingPhotos extends Command
{
    protected $signature = 'photos:compress-existing {--queue : Dispatch to queue instead of running inline} {--force : Recompress all photos including already completed ones}';
    protected $description = 'Compress all existing photos that have not been processed yet';

    public function handle(): int
    {
        $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];

        $query = UserContent::whereIn('file_type', $imageMimeTypes);

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('photo_status')
                  ->orWhere('photo_status', 'failed');
            });
        }

        $photos = $query->get();

        if ($photos->isEmpty()) {
            $this->info('No photos to compress.');
            return self::SUCCESS;
        }

        $this->info("Found {$photos->count()} photo(s) to compress.");
        $bar = $this->output->createProgressBar($photos->count());
        $bar->start();

        foreach ($photos as $index => $photo) {
            $percent = $photos->count() > 0 ? (int) round((($index + 1) / $photos->count()) * 100) : 100;
            $size = $this->formatFileSize((int) $photo->file_size);

            if ($this->output->isVerbose()) {
                $this->line(sprintf(
                    'Processing %s [%s] (%s) - %d%% complete',
                    $photo->file_name ?? $photo->file_path,
                    $photo->file_type,
                    $size,
                    $percent
                ));
            }

            if ($this->option('queue')) {
                ProcessImageCompression::dispatch($photo->file_path, $photo->id);
            } else {
                ProcessImageCompression::dispatchSync($photo->file_path, $photo->id);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

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

        return sprintf('size: %.2f %s', $bytes, $units[$index]);
    }
}
