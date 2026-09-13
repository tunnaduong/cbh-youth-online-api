<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Yoyo AI has no client to whisper "typing" the way humans do (see
 * ChatProvider.js/ChatSocketContext.js's channel.whisper('typing', ...)),
 * so its typing indicator has to be a real broadcast fired by the queued
 * GenerateAiChatReply job around its (potentially several-second) Groq call.
 * ShouldBroadcastNow (not the queued ShouldBroadcast) so it isn't itself
 * delayed by the queue the job is already running on.
 */
class AiTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationId;
    public $userId;
    public $avatarUrl;
    public $starting;

    public function __construct($conversationId, $userId, $avatarUrl, bool $starting)
    {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->avatarUrl = $avatarUrl;
        $this->starting = $starting;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs()
    {
        return 'ai.typing';
    }

    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'avatar_url' => $this->avatarUrl,
            'starting' => $this->starting,
        ];
    }
}
