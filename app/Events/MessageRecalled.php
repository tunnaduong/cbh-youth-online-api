<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRecalled implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $conversationId;
    public int $messageId;

    public function __construct(int $conversationId, int $messageId)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.recalled';
    }
}
