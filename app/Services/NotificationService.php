<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSettings;
use App\Models\Topic;
use App\Models\TopicComment;
use App\Models\Story;
use App\Models\Message;
use App\Services\PushNotificationService;

/**
 * Service for creating and managing notifications.
 */
class NotificationService
{
  /**
   * Create a notification and send push notification if subscription exists.
   *
   * @param array $notificationData
   * @return Notification|null
   */
  private static function createAndPushNotification(array $notificationData): ?Notification
  {
    $notification = Notification::create($notificationData);

    if ($notification) {
      // Send push notification asynchronously (don't wait for it)
      try {
        PushNotificationService::sendToUser($notification->user_id, $notification);
      } catch (\Exception $e) {
        // Log error but don't fail notification creation
        \Illuminate\Support\Facades\Log::error('Failed to send push notification', [
          'notification_id' => $notification->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return $notification;
  }

  /**
   * Check if a user should receive notifications based on their settings.
   *
   * @param int $userId
   * @param string $type
   * @return bool
   */
  protected static function shouldNotify(int $userId, string $type): bool
  {
    $settings = NotificationSettings::where('user_id', $userId)->first();

    if (!$settings) {
      // Default to notify if no settings exist
      return true;
    }

    // If user has disabled all notifications
    if ($settings->notify_type === 'none') {
      return false;
    }

    // If user only wants direct mentions
    if ($settings->notify_type === 'direct_mentions') {
      return in_array($type, ['mentioned']);
    }

    // Default: notify for all types
    return true;
  }

  /**
   * Create a notification for a topic being liked.
   *
   * @param Topic $topic
   * @param int $actorId User who liked the topic
   * @return Notification|null
   */
  public static function createTopicLikedNotification(Topic $topic, int $actorId): ?Notification
  {
    // Don't notify if user likes their own topic
    if ($topic->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($topic->user_id, 'topic_liked')) {
      return null;
    }

    return self::createAndPushNotification([
      'user_id' => $topic->user_id,
      'actor_id' => $actorId,
      'type' => 'topic_liked',
      'notifiable_type' => Topic::class,
      'notifiable_id' => $topic->id,
      'data' => [
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'url' => "/topics/{$topic->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a comment being liked.
   *
   * @param TopicComment $comment
   * @param int $actorId User who liked the comment
   * @return Notification|null
   */
  public static function createCommentLikedNotification(TopicComment $comment, int $actorId): ?Notification
  {
    // Don't notify if user likes their own comment
    if ($comment->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($comment->user_id, 'comment_liked')) {
      return null;
    }

    $topic = $comment->topic;
    $commentExcerpt = mb_substr(strip_tags($comment->comment ?? $comment->comment_html ?? ''), 0, 100);

    return self::createAndPushNotification([
      'user_id' => $comment->user_id,
      'actor_id' => $actorId,
      'type' => 'comment_liked',
      'notifiable_type' => TopicComment::class,
      'notifiable_id' => $comment->id,
      'data' => [
        'comment_id' => $comment->id,
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'comment_excerpt' => $commentExcerpt,
        'url' => "/topics/{$topic->id}#comment-{$comment->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a comment being replied to.
   *
   * @param TopicComment $reply The reply comment
   * @param TopicComment $parentComment The comment being replied to
   * @param int $actorId User who created the reply
   * @return Notification|null
   */
  public static function createCommentRepliedNotification(TopicComment $reply, TopicComment $parentComment, int $actorId): ?Notification
  {
    // Don't notify if user replies to their own comment
    if ($parentComment->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($parentComment->user_id, 'comment_replied')) {
      return null;
    }

    $topic = $reply->topic;
    $replyExcerpt = mb_substr(strip_tags($reply->comment ?? $reply->comment_html ?? ''), 0, 100);

    return self::createAndPushNotification([
      'user_id' => $parentComment->user_id,
      'actor_id' => $actorId,
      'type' => 'comment_replied',
      'notifiable_type' => TopicComment::class,
      'notifiable_id' => $reply->id,
      'data' => [
        'reply_id' => $reply->id,
        'parent_comment_id' => $parentComment->id,
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'reply_excerpt' => $replyExcerpt,
        'url' => "/topics/{$topic->id}#comment-{$reply->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a topic being commented on.
   *
   * @param Topic $topic
   * @param TopicComment $comment The new comment
   * @param int $actorId User who created the comment
   * @return Notification|null
   */
  public static function createTopicCommentedNotification(Topic $topic, TopicComment $comment, int $actorId): ?Notification
  {
    // Don't notify if user comments on their own topic
    if ($topic->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($topic->user_id, 'topic_commented')) {
      return null;
    }

    $commentExcerpt = mb_substr(strip_tags($comment->comment ?? $comment->comment_html ?? ''), 0, 100);

    return self::createAndPushNotification([
      'user_id' => $topic->user_id,
      'actor_id' => $actorId,
      'type' => 'topic_commented',
      'notifiable_type' => TopicComment::class,
      'notifiable_id' => $comment->id,
      'data' => [
        'comment_id' => $comment->id,
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'comment_excerpt' => $commentExcerpt,
        'url' => "/topics/{$topic->id}#comment-{$comment->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a user being mentioned.
   *
   * @param int $mentionedUserId
   * @param mixed $mentionable The entity where mention occurred (Topic or TopicComment)
   * @param int $actorId User who mentioned
   * @return Notification|null
   */
  public static function createMentionedNotification(int $mentionedUserId, $mentionable, int $actorId): ?Notification
  {
    // Don't notify if user mentions themselves
    if ($mentionedUserId === $actorId) {
      return null;
    }

    if (!self::shouldNotify($mentionedUserId, 'mentioned')) {
      return null;
    }

    $data = ['url' => '/'];
    $notifiableType = null;
    $notifiableId = null;

    if ($mentionable instanceof Topic) {
      $data = [
        'topic_id' => $mentionable->id,
        'topic_title' => $mentionable->title,
        'url' => "/topics/{$mentionable->id}",
      ];
      $notifiableType = Topic::class;
      $notifiableId = $mentionable->id;
    } elseif ($mentionable instanceof TopicComment) {
      $topic = $mentionable->topic;
      $data = [
        'comment_id' => $mentionable->id,
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'url' => "/topics/{$topic->id}#comment-{$mentionable->id}",
      ];
      $notifiableType = TopicComment::class;
      $notifiableId = $mentionable->id;
    }

    return self::createAndPushNotification([
      'user_id' => $mentionedUserId,
      'actor_id' => $actorId,
      'type' => 'mentioned',
      'notifiable_type' => $notifiableType,
      'notifiable_id' => $notifiableId,
      'data' => $data,
    ]);
  }

  /**
   * Create a notification for a topic being pinned.
   *
   * @param Topic $topic
   * @return Notification|null
   */
  public static function createTopicPinnedNotification(Topic $topic): ?Notification
  {
    if (!self::shouldNotify($topic->user_id, 'topic_pinned')) {
      return null;
    }

    return self::createAndPushNotification([
      'user_id' => $topic->user_id,
      'actor_id' => null, // System action
      'type' => 'topic_pinned',
      'notifiable_type' => Topic::class,
      'notifiable_id' => $topic->id,
      'data' => [
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'url' => "/topics/{$topic->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a topic being moved.
   *
   * @param Topic $topic
   * @param string $oldSubforumName
   * @param string $newSubforumName
   * @return Notification|null
   */
  public static function createTopicMovedNotification(Topic $topic, string $oldSubforumName, string $newSubforumName): ?Notification
  {
    if (!self::shouldNotify($topic->user_id, 'topic_moved')) {
      return null;
    }

    return self::createAndPushNotification([
      'user_id' => $topic->user_id,
      'actor_id' => null, // System action
      'type' => 'topic_moved',
      'notifiable_type' => Topic::class,
      'notifiable_id' => $topic->id,
      'data' => [
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'old_subforum' => $oldSubforumName,
        'new_subforum' => $newSubforumName,
        'url' => "/topics/{$topic->id}",
      ],
    ]);
  }

  /**
   * Create a notification for a topic being closed.
   *
   * @param Topic $topic
   * @return Notification|null
   */
  public static function createTopicClosedNotification(Topic $topic): ?Notification
  {
    if (!self::shouldNotify($topic->user_id, 'topic_closed')) {
      return null;
    }

    return self::createAndPushNotification([
      'user_id' => $topic->user_id,
      'actor_id' => null, // System/admin action
      'type' => 'topic_closed',
      'notifiable_type' => Topic::class,
      'notifiable_id' => $topic->id,
      'data' => [
        'topic_id' => $topic->id,
        'topic_title' => $topic->title,
        'url' => "/topics/{$topic->id}",
      ],
    ]);
  }

  /**
   * Create a system notification (rank up, badge, points, etc.).
   *
   * @param int $userId
   * @param string $type (rank_up, badge_earned, points_earned, etc.)
   * @param array $data Additional data
   * @return Notification|null
   */
  public static function createSystemNotification(int $userId, string $type, array $data = []): ?Notification
  {
    if (!self::shouldNotify($userId, $type)) {
      return null;
    }

    return self::createAndPushNotification([
      'user_id' => $userId,
      'actor_id' => null, // System action
      'type' => $type,
      'notifiable_type' => null,
      'notifiable_id' => null,
      'data' => $data,
    ]);
  }

  /**
   * Create a notification for content being reported/hidden/deleted.
   *
   * @param int $userId
   * @param string $type (content_reported, content_hidden, content_deleted)
   * @param mixed $content Topic or TopicComment
   * @param string $reason
   * @return Notification|null
   */
  public static function createContentModerationNotification(int $userId, string $type, $content, string $reason = ''): ?Notification
  {
    if (!self::shouldNotify($userId, $type)) {
      return null;
    }

    $data = ['reason' => $reason];

    if ($content instanceof Topic) {
      $data['topic_id'] = $content->id;
      $data['topic_title'] = $content->title;
      $data['url'] = "/topics/{$content->id}";
    } elseif ($content instanceof TopicComment) {
      $topic = $content->topic;
      $data['comment_id'] = $content->id;
      $data['topic_id'] = $topic->id;
      $data['topic_title'] = $topic->title;
      $data['url'] = "/topics/{$topic->id}#comment-{$content->id}";
    }

    return self::createAndPushNotification([
      'user_id' => $userId,
      'actor_id' => null, // Admin action
      'type' => $type,
      'notifiable_type' => get_class($content),
      'notifiable_id' => $content->id,
      'data' => $data,
    ]);
  }

  /**
   * Create a welcome notification for a user.
   *
   * @param int $userId
   * @return Notification|null
   */
  public static function createWelcomeNotification(int $userId): ?Notification
  {
    return self::createAndPushNotification([
      'user_id' => $userId,
      'actor_id' => null, // System action
      'type' => 'system_message',
      'notifiable_type' => null,
      'notifiable_id' => null,
      'data' => [
        'message' => 'Chào mừng bạn đến với Diễn đàn học sinh Chuyên Biên Hòa! Hãy cùng chia sẻ, học hỏi và kết nối với những người bạn tuyệt vời nơi đây.',
        'url' => '/',
      ],
    ]);
  }

  /**
   * Parse mentions from text (@username).
   *
   * @param string $text
   * @return array Array of usernames (without @)
   */
  public static function parseMentions(string $text): array
  {
    preg_match_all('/@(\w+)/', $text, $matches);
    return array_unique($matches[1] ?? []);
  }

  /**
   * Create a notification for a story being reacted to.
   *
   * @param Story $story
   * @param int $actorId User who reacted to the story
   * @param string $reactionType Type of reaction (like, love, haha, wow, sad, angry)
   * @return Notification|null
   */
  public static function createStoryReactionNotification(Story $story, int $actorId, string $reactionType): ?Notification
  {
    // Don't notify if user reacts to their own story
    if ($story->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($story->user_id, 'story_reacted')) {
      return null;
    }

    // Map reaction types to emoji for display
    $reactionEmojis = [
      'like' => '👍',
      'love' => '❤️',
      'haha' => '😆',
      'wow' => '😮',
      'sad' => '😢',
      'angry' => '😡',
    ];

    return self::createAndPushNotification([
      'user_id' => $story->user_id,
      'actor_id' => $actorId,
      'type' => 'story_reacted',
      'notifiable_type' => Story::class,
      'notifiable_id' => $story->id,
      'data' => [
        'story_id' => $story->id,
        'reaction_type' => $reactionType,
        'reaction_emoji' => $reactionEmojis[$reactionType] ?? '👍',
        'url' => '/', // Will navigate to story in mobile app
      ],
    ]);
  }

  /**
   * Create a notification for a story being replied to.
   *
   * @param Story $story
   * @param Message $message The reply message
   * @param int $actorId User who replied to the story
   * @return Notification|null
   */
  public static function createStoryReplyNotification(Story $story, Message $message, int $actorId): ?Notification
  {
    // Don't notify if user replies to their own story
    if ($story->user_id === $actorId) {
      return null;
    }

    if (!self::shouldNotify($story->user_id, 'story_replied')) {
      return null;
    }

    $messageExcerpt = mb_substr($message->content, 0, 100);

    return self::createAndPushNotification([
      'user_id' => $story->user_id,
      'actor_id' => $actorId,
      'type' => 'story_replied',
      'notifiable_type' => Message::class,
      'notifiable_id' => $message->id,
      'data' => [
        'story_id' => $story->id,
        'message_id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'message_excerpt' => $messageExcerpt,
        'url' => '/chat?conversation=' . $message->conversation_id, // Will navigate to conversation in mobile app
      ],
    ]);
  }
}
