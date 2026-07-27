<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Topic;
use App\Models\TopicComment;
use Illuminate\Console\Command;

class CleanupOrphanedNotifications extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'notifications:cleanup-orphaned {--dry-run : Only report how many would be deleted, without deleting}';

  /**
   * Mô tả command
   */
  protected $description = 'Xóa các notification trỏ đến bài viết/bình luận đã bị xóa trước đây (dữ liệu cũ, trước khi có dọn dẹp tự động)';

  public function handle()
  {
    $dryRun = $this->option('dry-run');

    $orphanedTopicNotifications = Notification::where('notifiable_type', Topic::class)
      ->whereNotIn('notifiable_id', Topic::pluck('id'));

    $orphanedCommentNotifications = Notification::where('notifiable_type', TopicComment::class)
      ->whereNotIn('notifiable_id', TopicComment::pluck('id'));

    $topicCount = $orphanedTopicNotifications->count();
    $commentCount = $orphanedCommentNotifications->count();

    if ($dryRun) {
      $this->info("🔍 [Dry run] Sẽ xóa {$topicCount} notification của bài viết đã xóa và {$commentCount} notification của bình luận đã xóa.");
      return;
    }

    $orphanedTopicNotifications->delete();
    $orphanedCommentNotifications->delete();

    $this->info("✅ Đã xóa {$topicCount} notification của bài viết đã xóa và {$commentCount} notification của bình luận đã xóa.");
  }
}
