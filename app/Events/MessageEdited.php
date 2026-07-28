<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $conversationId;
    public int $messageId;
    public string $content;
    public string $updatedAt;

    public function __construct(int $conversationId, int $messageId, string $content, string $updatedAt)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
        $this->content = $content;
        $this->updatedAt = $updatedAt;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
    }
}
