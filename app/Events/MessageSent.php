<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a new message is sent.
 */
class MessageSent implements ShouldBroadcast
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
     * The message that was sent.
     *
     * @var mixed
     */
    public $message;

    /**
     * Create a new event instance.
     *
     * @param  int  $conversationId
     * @param  mixed  $message
     * @return void
     */
    public function __construct($conversationId, $message)
    {
        $this->conversationId = $conversationId;
        $this->message = $message;
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
        return 'message.sent';
    }
}
