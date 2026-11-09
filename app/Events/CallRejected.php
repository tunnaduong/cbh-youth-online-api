<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a call is rejected or callee is busy.
 */
class CallRejected implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $conversationId;
  public $callId;
  public $reason;
  public $participantIds;

  public function __construct(int $conversationId, int $callId, string $reason = 'rejected', array $participantIds = [])
  {
    $this->conversationId = $conversationId;
    $this->callId = $callId;
    $this->reason = $reason;
    $this->participantIds = $participantIds;
  }

  public function broadcastOn()
  {
    $channels = [
      new PresenceChannel('chat.' . $this->conversationId),
    ];

    foreach (array_unique($this->participantIds) as $userId) {
      if ($userId) {
        $channels[] = new PrivateChannel('App.Models.User.' . $userId);
      }
    }

    return $channels;
  }

  public function broadcastAs()
  {
    return 'call.rejected';
  }

  public function broadcastWith()
  {
    return [
      'conversation_id' => $this->conversationId,
      'call_id' => $this->callId,
      'reason' => $this->reason,
    ];
  }
}
