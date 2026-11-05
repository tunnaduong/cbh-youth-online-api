<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
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

  public function __construct(int $conversationId, int $callId, int $senderId, string $type, $payload)
  {
    $this->conversationId = $conversationId;
    $this->callId = $callId;
    $this->senderId = $senderId;
    $this->type = $type;
    $this->payload = $payload;
  }

  public function broadcastOn()
  {
    return new PresenceChannel('chat.' . $this->conversationId);
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
