<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Relays WebRTC signaling (SDP offer/answer and ICE candidates) between peers.
 */
class CallSignaled implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $conversationId;
  public $callId;
  public $senderId;
  public $type;
  public $payload;
  public $recipientIds;

  public function __construct(int $conversationId, int $callId, int $senderId, string $type, $payload, array $recipientIds = [])
  {
    $this->conversationId = $conversationId;
    $this->callId = $callId;
    $this->senderId = $senderId;
    $this->type = $type;
    $this->payload = $payload;
    $this->recipientIds = $recipientIds;
  }

  public function broadcastOn()
  {
    $channels = [
      new PresenceChannel('chat.' . $this->conversationId),
    ];

    foreach (array_unique($this->recipientIds) as $userId) {
      if ($userId) {
        $channels[] = new PrivateChannel('App.Models.User.' . $userId);
      }
    }

    return $channels;
  }

  public function broadcastAs()
  {
    return 'call.signaled';
  }

  public function broadcastWith()
  {
    return [
      'conversation_id' => $this->conversationId,
      'call_id' => $this->callId,
      'sender_id' => $this->senderId,
      'type' => $this->type,
      'payload' => $this->payload,
    ];
  }
}
