<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an incoming voice/video call is initiated.
 * Implements ShouldBroadcastNow for immediate sub-50ms ringing latency.
 */
class CallInitiated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $conversationId;
  public $callData;
  public $receiverId;

  public function __construct(int $conversationId, array $callData, ?int $receiverId = null)
  {
    $this->conversationId = $conversationId;
    $this->callData = $callData;
    $this->receiverId = $receiverId;
  }

  /**
   * Broadcast on the conversation presence channel and the receiver's personal user channel.
   */
  public function broadcastOn()
  {
    $channels = [
      new PresenceChannel('chat.' . $this->conversationId),
    ];

    if ($this->receiverId) {
      $channels[] = new PrivateChannel('App.Models.User.' . $this->receiverId);
    }

    return $channels;
  }

  /**
   * Event name listened by the client.
   */
  public function broadcastAs()
  {
    return 'call.initiated';
  }

  /**
   * Custom payload sent over the WebSocket.
   */
  public function broadcastWith()
  {
    return [
      'conversation_id' => $this->conversationId,
      'call' => $this->callData,
    ];
  }
}
