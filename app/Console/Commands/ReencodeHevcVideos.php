<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoCompression;
use App\Models\UserContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Videos compressed before ProcessVideoCompression switched to H.264 are
 * H.265 (HEVC), which Chrome and Firefox play as sound only. This finds
 * every H.265 video on the public disk (posts, chat, stories) and runs it
 * through the compression job again, which re-encodes it in place.
 */
class ReencodeHevcVideos extends Command
{
    protected $signature = 'videos:reencode-hevc
        {--queue : Dispatch to the queue instead of running inline}
        {--dry-run : Only list the videos that would be re-encoded}';

    protected $description = 'Re-encode H.265 videos to H.264 so they play in every browser';

    private const EXTENSIONS = ['mp4', 'mov', 'm4v', 'mkv', 'webm', 'avi'];

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $files = collect($disk->allFiles())
            ->filter(fn($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            // Leftovers of a run that was interrupted.
            ->reject(fn($path) => str_ends_with($path, '.tmp.mp4'))
            ->values();

        $this->info("Checking {$files->count()} video file(s)...");

        $hevc = $files->filter(fn($path) => $this->videoCodec($disk->path($path)) === 'hevc')->values();

        if ($hevc->isEmpty()) {
            $this->info('No H.265 videos found.');
            return self::SUCCESS;
        }

        $this->info("Found {$hevc->count()} H.265 video(s).");

        if ($this->option('dry-run')) {
            $hevc->each(fn($path) => $this->line($path));
            return self::SUCCESS;
        }

        $contentIds = UserContent::whereIn('file_path', $hevc->all())->pluck('id', 'file_path');

        $bar = $this->output->createProgressBar($hevc->count());
        $bar->start();

        foreach ($hevc as $path) {
            $contentId = $contentIds[$path] ?? null;

            if ($this->option('queue')) {
                ProcessVideoCompression::dispatch($path, $contentId);
            } else {
                ProcessVideoCompression::dispatchSync($path, $contentId);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info($this->option('queue') ? 'Queued.' : 'Done!');

        return self::SUCCESS;
    }

    private function videoCodec(string $absolutePath): string
    {
        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($absolutePath)
        );

        return strtolower(trim((string) shell_exec($cmd)));
    }
}
