<?php

namespace App\Jobs;

use App\Models\AdminBroadcast;
use App\Models\Notification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliver an admin broadcast: inbox notifications + web push + Expo push.
 */
class SendAdminBroadcast implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 1800;
  public $tries = 1;

  public function __construct(public int $broadcastId)
  {
  }

  public function handle(): void
  {
    $broadcast = AdminBroadcast::find($this->broadcastId);
    if (!$broadcast || $broadcast->status !== 'queued') {
      return;
    }

    $broadcast->update(['status' => 'sending']);

    try {
      $data = array_filter([
        'type' => 'system_message',
        'url' => $broadcast->url ?: ($broadcast->topic_id ? null : '/'),
        'topic_id' => $broadcast->topic_id,
        'broadcast_id' => $broadcast->id,
      ], fn($v) => $v !== null);

      $channels = $broadcast->channels ?? [];
      $recipients = 0;
      $webSent = 0;
      $mobileSent = 0;

      AdminBroadcast::recipientsQuery($broadcast->audience, $broadcast->audience_value)
        ->orderBy('id')
        ->chunkById(500, function ($users) use ($broadcast, $data, $channels, &$recipients, &$webSent, &$mobileSent) {
          $ids = $users->pluck('id')->all();
          $recipients += count($ids);

          if ($broadcast->save_to_inbox) {
            $now = now();
            Notification::insert(array_map(fn($id) => [
              'user_id' => $id,
              'actor_id' => null,
              'type' => 'system_message',
              'notifiable_type' => null,
              'notifiable_id' => null,
              'data' => json_encode([
                'message' => "{$broadcast->title}: {$broadcast->body}",
                'title' => $broadcast->title,
                'body' => $broadcast->body,
              ] + $data, JSON_UNESCAPED_UNICODE),
              'created_at' => $now,
              'updated_at' => $now,
            ], $ids));
          }

          if (in_array('web', $channels, true)) {
            $webSent += PushNotificationService::broadcastWebPush($ids, $broadcast->title, $broadcast->body, $data);
          }
          if (in_array('mobile', $channels, true)) {
            $mobileSent += PushNotificationService::broadcastExpoPush($ids, $broadcast->title, $broadcast->body, $data);
          }

          $broadcast->update([
            'recipients_count' => $recipients,
            'web_sent' => $webSent,
            'mobile_sent' => $mobileSent,
          ]);
        });

      $broadcast->update(['status' => 'sent', 'sent_at' => now()]);
    } catch (\Throwable $e) {
      Log::error('Admin broadcast failed', ['broadcast_id' => $broadcast->id, 'error' => $e->getMessage()]);
      $broadcast->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
  }
}
