<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoCompression;
use App\Models\UserContent;
use Illuminate\Console\Command;

class CompressExistingVideos extends Command
{
    protected $signature = 'videos:compress-existing {--queue : Dispatch to queue instead of running inline}';
    protected $description = 'Compress all existing videos that have not been processed yet';

    public function handle(): int
    {
        $videos = UserContent::whereIn('file_type', ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-matroska', 'video/webm'])
            ->where(function ($q) {
                $q->whereNull('video_status')
                  ->orWhere('video_status', 'failed');
            })
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No videos to compress.');
            return self::SUCCESS;
        }

        $this->info("Found {$videos->count()} video(s) to compress.");
        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        foreach ($videos as $video) {
            if ($this->option('queue')) {
                ProcessVideoCompression::dispatch($video->file_path, $video->id);
            } else {
                ProcessVideoCompression::dispatchSync($video->file_path, $video->id);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }
}
