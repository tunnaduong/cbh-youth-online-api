<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when the recipient answers an incoming call.
 */
class CallAnswered implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $conversationId;
  public $callData;
  public $participantIds;

  public function __construct(int $conversationId, array $callData, array $participantIds = [])
  {
    $this->conversationId = $conversationId;
    $this->callData = $callData;
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
    return 'call.answered';
  }

  public function broadcastWith()
  {
    return [
      'conversation_id' => $this->conversationId,
      'call' => $this->callData,
    ];
  }
}
