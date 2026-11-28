<?php

namespace App\Console\Commands;

use App\Models\Topic;
use App\Services\TopicCoverService;
use Illuminate\Console\Command;

class GenerateTopicCovers extends Command
{
  protected $signature = 'topics:generate-covers
    {--id=* : Only these topic IDs}
    {--force : Redraw covers that already exist}
    {--limit=0 : Stop after this many topics}';

  protected $description = 'Make cover pictures for posts without a photo (first video frame, or the markdown text drawn as a page)';

  public function handle(TopicCoverService $covers): int
  {
    $query = Topic::query()->orderBy('id');

    $explicitIds = array_values(array_filter(
      array_map('trim', explode(',', implode(',', (array) $this->option('id')))),
      fn($id) => $id !== ''
    ));
    if ($explicitIds) {
      $query->whereIn('id', $explicitIds);
    }
    if (!$this->option('force')) {
      $query->where(fn($q) => $q->whereNull('cover_image')->orWhere('cover_image', ''));
    }

    $limit = (int) $this->option('limit');
    $done = 0;
    $failed = 0;

    foreach ($query->lazyById(100) as $topic) {
      if ($limit && ($done + $failed) >= $limit) {
        break;
      }

      if (TopicCoverService::hasUsablePhoto($topic)) {
        if ($explicitIds) {
          $failed++;
          $this->warn("#{$topic->id}: skipped (topic already has photo)");
        }
        continue;
      }

      try {
        $path = $covers->generate($topic);
      } catch (\Throwable $e) {
        $failed++;
        $this->warn("#{$topic->id}: failed ({$e->getMessage()})");
        continue;
      }

      if ($path) {
        $done++;
        $this->line("#{$topic->id} -> {$path}");
      } else {
        $failed++;
        $this->warn("#{$topic->id}: skipped (no text or drawing failed)");
      }
    }

    $this->info("Done: {$done} covers, {$failed} skipped.");
    return self::SUCCESS;
  }
}
