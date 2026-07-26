<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a message's reactions change (added, updated, or removed).
 */
class MessageReacted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The ID of the conversation.
     *
     * @var int
     */
    public $conversationId;

    /**
     * The ID of the message that was reacted to.
     *
     * @var int
     */
    public $messageId;

    /**
     * The updated reaction summary for the message.
     *
     * @var array
     */
    public $reactions;

    /**
     * Create a new event instance.
     *
     * @param  int  $conversationId
     * @param  int  $messageId
     * @param  array  $reactions
     * @return void
     */
    public function __construct($conversationId, $messageId, array $reactions)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
        $this->reactions = $reactions;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|\Illuminate\Broadcasting\Channel[]
     */
    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->conversationId);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.reacted';
    }

    /**
     * Custom payload sent over the WebSocket.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'reactions' => $this->reactions,
        ];
    }
}
