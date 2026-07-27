<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

class CleanupExpiredStories extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'stories:cleanup-expired';

  /**
   * Mô tả command
   */
  protected $description = 'Xóa mềm các story đã hết hạn (không pin) và dọn dẹp notification liên quan đến chúng';

  public function handle()
  {
    $count = 0;

    Story::whereNotNull('expires_at')
      ->where('pinned', false)
      ->where('expires_at', '<=', now())
      ->chunkById(100, function ($stories) use (&$count) {
        foreach ($stories as $story) {
          // Triggers Story::boot()'s deleted event, which also removes
          // any notifications referencing this story.
          $story->delete();
          $count++;
        }
      });

    $this->info("✅ Đã dọn dẹp {$count} story đã hết hạn.");
  }
}
