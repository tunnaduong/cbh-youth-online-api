<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel; // <-- Updated to PrivateChannel
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
     * Broadcast on a private channel matching routes/channels.php
     */
    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->conversationId);
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