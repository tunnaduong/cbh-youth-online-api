<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageCompression;
use App\Models\UserContent;
use Illuminate\Console\Command;

class CompressExistingPhotos extends Command
{
    protected $signature = 'photos:compress-existing {--queue : Dispatch to queue instead of running inline}';
    protected $description = 'Compress all existing photos that have not been processed yet';

    public function handle(): int
    {
        $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];

        $photos = UserContent::whereIn('file_type', $imageMimeTypes)
            ->where(function ($q) {
                $q->whereNull('photo_status')
                  ->orWhere('photo_status', 'failed');
            })
            ->get();

        if ($photos->isEmpty()) {
            $this->info('No photos to compress.');
            return self::SUCCESS;
        }

        $this->info("Found {$photos->count()} photo(s) to compress.");
        $bar = $this->output->createProgressBar($photos->count());
        $bar->start();

        foreach ($photos as $photo) {
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
}
