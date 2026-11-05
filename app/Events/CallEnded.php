<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an ongoing call is ended or hung up.
 */
class CallEnded implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $conversationId;
  public $callData;

  public function __construct(int $conversationId, array $callData)
  {
    $this->conversationId = $conversationId;
    $this->callData = $callData;
  }

  public function broadcastOn()
  {
    return new PresenceChannel('chat.' . $this->conversationId);
  }

  public function broadcastAs()
  {
    return 'call.ended';
  }

  public function broadcastWith()
  {
    return [
      'conversation_id' => $this->conversationId,
      'call' => $this->callData,
    ];
  }
}
