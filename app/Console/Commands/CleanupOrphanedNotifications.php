<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Story;
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
  protected $description = 'Xóa các notification trỏ đến bài viết/bình luận/story đã bị xóa (hoặc hết hạn) trước đây (dữ liệu cũ, trước khi có dọn dẹp tự động)';

  public function handle()
  {
    $dryRun = $this->option('dry-run');

    $orphanedTopicNotifications = Notification::where('notifiable_type', Topic::class)
      ->whereNotIn('notifiable_id', Topic::pluck('id'));

    $orphanedCommentNotifications = Notification::where('notifiable_type', TopicComment::class)
      ->whereNotIn('notifiable_id', TopicComment::pluck('id'));

    // Story::pluck('id') excludes soft-deleted rows (SoftDeletes global scope),
    // so this also covers stories deleted by their owner before this cleanup existed.
    // Already-expired-but-not-yet-purged stories are handled by stories:cleanup-expired.
    $existingStoryIds = Story::pluck('id');

    $orphanedStoryNotifications = Notification::where('notifiable_type', Story::class)
      ->whereNotIn('notifiable_id', $existingStoryIds);

    // "Someone replied to your story" notifications are stored against the
    // reply Message (notifiable_type = Message::class), with the story only
    // referenced inside the data JSON payload — so they need a separate query.
    // This only removes the notification row, never the underlying Message/chat.
    $orphanedStoryReplyNotifications = Notification::where('type', 'story_replied')
      ->whereNotIn('data->story_id', $existingStoryIds);

    $topicCount = $orphanedTopicNotifications->count();
    $commentCount = $orphanedCommentNotifications->count();
    $storyCount = $orphanedStoryNotifications->count();
    $storyReplyCount = $orphanedStoryReplyNotifications->count();

    if ($dryRun) {
      $this->info("🔍 [Dry run] Sẽ xóa {$topicCount} notification của bài viết đã xóa, {$commentCount} notification của bình luận đã xóa, {$storyCount} notification của story đã xóa, và {$storyReplyCount} notification trả lời story đã xóa.");
      return;
    }

    $orphanedTopicNotifications->delete();
    $orphanedCommentNotifications->delete();
    $orphanedStoryNotifications->delete();
    $orphanedStoryReplyNotifications->delete();

    $this->info("✅ Đã xóa {$topicCount} notification của bài viết đã xóa, {$commentCount} notification của bình luận đã xóa, {$storyCount} notification của story đã xóa, và {$storyReplyCount} notification trả lời story đã xóa.");
  }
}
