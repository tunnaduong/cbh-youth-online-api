<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
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

  public function __construct(int $conversationId, int $callId, string $reason = 'rejected')
  {
    $this->conversationId = $conversationId;
    $this->callId = $callId;
    $this->reason = $reason;
  }

  public function broadcastOn()
  {
    return new PresenceChannel('chat.' . $this->conversationId);
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
