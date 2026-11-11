<?php

namespace App\Console\Commands;

use App\Models\Topic;
use Illuminate\Console\Command;

class RandomPublishLearningTips extends Command
{
  protected $signature = 'learning-tips:random-publish {--limit=3}';
  protected $description = 'Randomly publish learning tips topics to keep content fresh';

  public function handle()
  {
    // Get all draft/pending learning tips topics (those created by seeder)
    // Filter by specific keywords to identify learning tips topics
    $allTips = Topic::where('title', 'like', '%🎯%')
      ->orWhere('title', 'like', '%🧠%')
      ->orWhere('title', 'like', '%💻%')
      ->orWhere('title', 'like', '%⏰%')
      ->orWhere('title', 'like', '%📚%')
      ->get();

    if ($allTips->isEmpty()) {
      $this->error('❌ No learning tips topics found. Run: php artisan db:seed --class=LearningTipsTopicSeeder');
      return 1;
    }

    $limit = (int) $this->option('limit');
    $randomTips = $allTips->random(min($limit, $allTips->count()));

    $published = 0;
    foreach ($randomTips as $topic) {
      // Only publish if not already published recently
      if (!$topic->hidden && $topic->updated_at->diffInDays() < 7) {
        $this->info("⏭️ Skipped: {$topic->title} (already published recently)");
        continue;
      }

      $topic->update([
        'hidden' => false, // Publish
        'updated_at' => now(),
      ]);

      $published++;
      $this->info("✅ Published: {$topic->title}");
    }

    $this->line('');
    $this->info("🎉 Successfully published {$published} learning tips topics!");

    return 0;
  }
}
