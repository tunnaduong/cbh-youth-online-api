<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a message is deleted.
 */
class MessageDeleted implements ShouldBroadcast
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
     * The ID of the message that was deleted.
     *
     * @var int
     */
    public $messageId;

    /**
     * Create a new event instance.
     *
     * @param  int  $conversationId
     * @param  int  $messageId
     * @return void
     */
    public function __construct($conversationId, $messageId)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
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
        return 'message.deleted';
    }
}
