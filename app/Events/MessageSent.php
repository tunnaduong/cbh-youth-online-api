<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationId;
    public $message;

    public function __construct($conversationId, $message)
    {
        $this->conversationId = $conversationId;
        $this->message = $message;
    }

    /**
     * Broadcast on the same presence channel as MessageRead/MessageDeleted so a single
     * Echo.join('chat.{id}') subscription receives all three chat events.
     */
    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->conversationId);
    }

    /**
     * Event name listened by the client
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Custom payload sent over the WebSocket
     */
    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
        ];
    }
}