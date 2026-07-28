<?php

namespace App\Console\Commands;

use App\Models\UserContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedCdnContent extends Command
{
    protected $signature = 'cdn:cleanup-orphaned {--dry-run : List rows that would be deleted without deleting them}';
    protected $description = 'Delete cyo_cdn_user_content rows whose files no longer exist on disk';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $rows = UserContent::all(['id', 'file_path', 'file_type']);

        $orphaned = $rows->filter(fn($row) => !$disk->exists($row->file_path));

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned records found.');
            return self::SUCCESS;
        }

        $this->info("Found {$orphaned->count()} orphaned record(s).");
        $this->table(['ID', 'file_path', 'file_type'], $orphaned->map(fn($r) => [$r->id, $r->file_path, $r->file_type]));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing deleted.');
            return self::SUCCESS;
        }

        UserContent::whereIn('id', $orphaned->pluck('id'))->delete();
        $this->info('Deleted.');

        return self::SUCCESS;
    }
}
