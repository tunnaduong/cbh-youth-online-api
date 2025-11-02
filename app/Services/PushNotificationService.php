<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSubscription;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Service for sending push notifications using Web Push Protocol.
 */
class PushNotificationService
{
  /**
   * Send a push notification to a specific subscription.
   *
   * @param \App\Models\NotificationSubscription $subscription
   * @param \App\Models\Notification $notification
   * @return bool
   */
  public static function sendPushNotification(NotificationSubscription $subscription, Notification $notification): bool
  {
    if (!$subscription->isValid()) {
      Log::warning("Skipping push notification - subscription expired", [
        'subscription_id' => $subscription->id,
        'notification_id' => $notification->id,
      ]);
      return false;
    }

    try {
      $vapidPublicKey = config('services.vapid.public_key');
      $vapidPrivateKey = config('services.vapid.private_key');
      $vapidSubject = config('services.vapid.subject');

      if (!$vapidPublicKey || !$vapidPrivateKey) {
        Log::warning("VAPID keys not configured", [
          'subscription_id' => $subscription->id,
          'notification_id' => $notification->id,
        ]);
        return false;
      }

      // Create WebPush instance with VAPID keys
      $webPush = new WebPush([
        'VAPID' => [
          'subject' => $vapidSubject,
          'publicKey' => $vapidPublicKey,
          'privateKey' => $vapidPrivateKey,
        ],
      ]);

      // Build push subscription object
      $pushSubscription = Subscription::create([
        'endpoint' => $subscription->endpoint,
        'keys' => [
          'p256dh' => $subscription->p256dh,
          'auth' => $subscription->auth,
        ],
      ]);

      // Build notification payload
      $payload = self::buildNotificationPayload($notification);
      $payloadJson = json_encode($payload);

      // Send push notification
      $result = $webPush->sendOneNotification(
        $pushSubscription,
        $payloadJson
      );

      // Flush to ensure notification is sent
      $webPush->flush();

      // Check result
      if ($result->isSuccess()) {
        Log::info("Push notification sent successfully", [
          'subscription_id' => $subscription->id,
          'notification_id' => $notification->id,
        ]);
        return true;
      } else {
        Log::warning("Failed to send push notification", [
          'subscription_id' => $subscription->id,
          'notification_id' => $notification->id,
          'reason' => $result->getReason(),
        ]);

        // If subscription is invalid (410 or 404), delete it
        $statusCode = $result->getResponse()?->getStatusCode();
        if (in_array($statusCode, [404, 410])) {
          $subscription->delete();
          Log::info("Deleted invalid subscription", [
            'subscription_id' => $subscription->id,
          ]);
        }

        return false;
      }
    } catch (\Exception $e) {
      Log::error("Error sending push notification", [
        'subscription_id' => $subscription->id,
        'notification_id' => $notification->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return false;
    }
  }

  /**
   * Send push notification to all valid subscriptions of a user.
   *
   * @param int $userId
   * @param \App\Models\Notification $notification
   * @return int Number of notifications sent successfully
   */
  public static function sendToUser(int $userId, Notification $notification): int
  {
    $subscriptions = NotificationSubscription::where('user_id', $userId)
      ->where(function ($query) {
        $query->whereNull('expires_at')
          ->orWhere('expires_at', '>', now());
      })
      ->get();

    $sentCount = 0;
    foreach ($subscriptions as $subscription) {
      if (self::sendPushNotification($subscription, $notification)) {
        $sentCount++;
      }
    }

    return $sentCount;
  }

  /**
   * Build notification payload for push notification.
   *
   * @param \App\Models\Notification $notification
   * @return array
   */
  public static function buildNotificationPayload(Notification $notification): array
  {
    $actor = $notification->actor;
    $data = $notification->data ?? [];

    // Build notification message based on type
    $message = self::getNotificationMessage($notification);

    $payload = [
      'title' => $message,
      'body' => $data['comment_excerpt'] ?? $data['topic_title'] ?? $data['message'] ?? '',
      'icon' => $actor ? (config('app.url') . "/v1.0/users/{$actor->username}/avatar") : '/images/icon.png',
      'badge' => '/images/badge.png',
      'tag' => "notification-{$notification->id}",
      'data' => [
        'notification_id' => $notification->id,
        'type' => $notification->type,
        'url' => $data['url'] ?? '/',
        'actor' => $actor ? [
          'id' => $actor->id,
          'username' => $actor->username,
          'profile_name' => $actor->profile->profile_name ?? $actor->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$actor->username}/avatar",
        ] : null,
      ],
      'requireInteraction' => false,
    ];

    // Add additional data from notification data
    if (isset($data['topic_id'])) {
      $payload['data']['topic_id'] = $data['topic_id'];
    }
    if (isset($data['comment_id'])) {
      $payload['data']['comment_id'] = $data['comment_id'];
    }

    return $payload;
  }

  /**
   * Get notification message based on type.
   *
   * @param \App\Models\Notification $notification
   * @return string
   */
  private static function getNotificationMessage(Notification $notification): string
  {
    $actor = $notification->actor;
    $actorName = $actor ? ($actor->profile->profile_name ?? $actor->username) : 'Ai đó';

    $messages = [
      'topic_liked' => "{$actorName} đã thích bài viết của bạn",
      'comment_liked' => "{$actorName} đã thích bình luận của bạn",
      'comment_replied' => "{$actorName} đã trả lời bình luận của bạn",
      'topic_commented' => "{$actorName} đã bình luận bài viết của bạn",
      'mentioned' => "{$actorName} đã nhắc đến bạn",
      'topic_pinned' => "Bài viết của bạn đã được ghim",
      'topic_moved' => "Bài viết của bạn đã được chuyển",
      'topic_closed' => "Bài viết của bạn đã bị đóng",
      'rank_up' => "Bạn đã được thăng hạng!",
      'badge_earned' => "Bạn đã nhận được huy hiệu",
      'points_earned' => "Bạn đã nhận được điểm thưởng",
      'content_reported' => "Nội dung của bạn đã bị báo cáo",
      'content_hidden' => "Nội dung của bạn đã bị ẩn",
      'content_deleted' => "Nội dung của bạn đã bị xóa",
      'system_message' => $notification->data['message'] ?? "Bạn có thông báo mới",
    ];

    return $messages[$notification->type] ?? "Bạn có thông báo mới";
  }

  /**
   * Send push notifications for chat messages to all participants.
   *
   * @param \App\Models\Conversation $conversation
   * @param array $messageData
   * @param int $senderId
   * @return int Number of notifications sent successfully
   */
  public static function sendChatPushNotifications(Conversation $conversation, array $messageData, int $senderId): int
  {
    try {
      Log::info("Sending chat push notifications", [
        'conversation_id' => $conversation->id,
        'message_id' => $messageData['id'] ?? null,
        'sender_id' => $senderId,
      ]);

      // Get all participants except the sender (only authenticated users, skip guests)
      // participants() relationship only returns valid AuthAccount records (no nulls due to foreign key)
      // But we need to filter out the sender - specify table name to avoid ambiguous column error
      $participants = $conversation->participants()
        ->where('cyo_auth_accounts.id', '!=', $senderId)
        ->get()
        ->filter(function ($participant) use ($senderId) {
          // Double check: only authenticated users (not null and not sender)
          return $participant->id && $participant->id != $senderId;
        });

      Log::info("Found participants for push notification", [
        'conversation_id' => $conversation->id,
        'participants_count' => $participants->count(),
        'participant_ids' => $participants->pluck('id')->toArray(),
      ]);

      if ($participants->isEmpty()) {
        Log::info("No authenticated participants to send push notifications to", [
          'conversation_id' => $conversation->id,
        ]);
        return 0;
      }

      $sentCount = 0;

      foreach ($participants as $participant) {
        // Skip if participant doesn't have valid id (should be filtered already, but double check)
        if (!$participant->id) {
          continue;
        }

        // Get all valid push subscriptions for this participant
        $subscriptions = NotificationSubscription::where('user_id', $participant->id)
          ->where(function ($query) {
            $query->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
          })
          ->get();

        Log::info("Found subscriptions for participant", [
          'participant_id' => $participant->id,
          'subscriptions_count' => $subscriptions->count(),
        ]);

        foreach ($subscriptions as $subscription) {
          if (!$subscription->isValid()) {
            continue;
          }

          // Build chat push notification payload
          $sender = $messageData['sender'] ?? [];
          $senderName = $sender['profile_name'] ?? $sender['username'] ?? 'Ai đó';

          $title = $conversation->type === 'group' && $conversation->name
            ? "{$senderName} trong {$conversation->name}"
            : $senderName;

          $body = $messageData['content'] ?? '';
          if (mb_strlen($body) > 50) {
            $body = mb_substr($body, 0, 50) . '...';
          }

          $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => $sender['avatar_url'] ?? '/images/icon.png',
            'badge' => '/images/badge.png',
            'tag' => "chat-{$conversation->id}-{$messageData['id']}",
            'data' => [
              'type' => 'chat_message',
              'conversation_id' => $conversation->id,
              'message_id' => $messageData['id'],
              'url' => "/chat?conversation={$conversation->id}",
            ],
            'requireInteraction' => false,
          ];

          // Send push notification using existing method
          if (self::sendRawPushNotification($subscription, $payload)) {
            $sentCount++;
          }
        }
      }

      Log::info("Chat push notifications sent", [
        'conversation_id' => $conversation->id,
        'message_id' => $messageData['id'] ?? null,
        'sent_count' => $sentCount,
      ]);

      return $sentCount;
    } catch (\Exception $e) {
      Log::error("Error sending chat push notifications", [
        'conversation_id' => $conversation->id,
        'message_id' => $messageData['id'] ?? null,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return 0;
    }
  }

  /**
   * Send a raw push notification payload to a subscription.
   *
   * @param \App\Models\NotificationSubscription $subscription
   * @param array $payload
   * @return bool
   */
  private static function sendRawPushNotification(NotificationSubscription $subscription, array $payload): bool
  {
    if (!$subscription->isValid()) {
      Log::warning("Skipping push notification - subscription expired", [
        'subscription_id' => $subscription->id,
      ]);
      return false;
    }

    try {
      $vapidPublicKey = config('services.vapid.public_key');
      $vapidPrivateKey = config('services.vapid.private_key');
      $vapidSubject = config('services.vapid.subject');

      if (!$vapidPublicKey || !$vapidPrivateKey) {
        Log::warning("VAPID keys not configured", [
          'subscription_id' => $subscription->id,
        ]);
        return false;
      }

      // Create WebPush instance with VAPID keys
      $webPush = new WebPush([
        'VAPID' => [
          'subject' => $vapidSubject,
          'publicKey' => $vapidPublicKey,
          'privateKey' => $vapidPrivateKey,
        ],
      ]);

      // Build push subscription object
      $pushSubscription = Subscription::create([
        'endpoint' => $subscription->endpoint,
        'keys' => [
          'p256dh' => $subscription->p256dh,
          'auth' => $subscription->auth,
        ],
      ]);

      // Send push notification
      $payloadJson = json_encode($payload);

      Log::debug("Sending push notification payload", [
        'subscription_id' => $subscription->id,
        'payload' => $payload,
        'payload_json' => $payloadJson,
        'payload_length' => strlen($payloadJson),
      ]);

      $result = $webPush->sendOneNotification(
        $pushSubscription,
        $payloadJson
      );

      // Flush to ensure notification is sent
      $webPush->flush();

      // Check result
      if ($result->isSuccess()) {
        Log::info("Push notification sent successfully", [
          'subscription_id' => $subscription->id,
        ]);
        return true;
      } else {
        Log::warning("Failed to send push notification", [
          'subscription_id' => $subscription->id,
          'reason' => $result->getReason(),
        ]);

        // If subscription is invalid (410 or 404), delete it
        $statusCode = $result->getResponse()?->getStatusCode();
        if (in_array($statusCode, [404, 410])) {
          $subscription->delete();
          Log::info("Deleted invalid subscription", [
            'subscription_id' => $subscription->id,
          ]);
        }

        return false;
      }
    } catch (\Exception $e) {
      Log::error("Error sending push notification", [
        'subscription_id' => $subscription->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return false;
    }
  }

}

