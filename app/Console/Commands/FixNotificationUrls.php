<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Topic;
use Illuminate\Console\Command;

class FixNotificationUrls extends Command
{
    protected $signature = 'notifications:fix-urls {--dry-run : Show what would be updated without making changes}';
    protected $description = 'Fix notification URLs from old /topics/{id} format to /{username}/posts/{id}-{slug}';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $notifications = Notification::whereRaw("CAST(data AS CHAR) LIKE '%/topics/%'")->get();

        if ($notifications->isEmpty()) {
            $this->info('No notifications with old URL format found.');
            return 0;
        }

        $this->info("Found {$notifications->count()} notifications to fix" . ($dryRun ? ' (dry run)' : '') . '.');

        $fixed = 0;
        $skipped = 0;

        foreach ($notifications as $notification) {
            $data = $notification->data;
            $oldUrl = $data['url'] ?? null;

            if (!$oldUrl || !preg_match('#^/topics/(\d+)(#.*)?$#', $oldUrl, $matches)) {
                $skipped++;
                continue;
            }

            $topicId = $matches[1];
            $anchor = $matches[2] ?? '';

            $topic = Topic::with('user')->find($topicId);

            if (!$topic || !$topic->user) {
                $this->warn("Skipping notification {$notification->id}: topic {$topicId} or its user not found.");
                $skipped++;
                continue;
            }

            $newUrl = $topic->getUrl() . $anchor;

            if ($dryRun) {
                $this->line("  [DRY RUN] notification {$notification->id}: {$oldUrl} → {$newUrl}");
            } else {
                $data['url'] = $newUrl;
                $notification->data = $data;
                $notification->save();
            }

            $fixed++;
        }

        $this->info("Fixed: {$fixed}, Skipped: {$skipped}");
        return 0;
    }
}
