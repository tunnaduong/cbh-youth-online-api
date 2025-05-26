<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'cyo_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
        'type',
        'file_url',
        'is_edited',
        'read_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent this message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Mark the message as read
     */
    public function markAsRead()
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Check if the message is read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Edit the message content
     */
    public function edit(string $newContent)
    {
        $this->update([
            'content' => $newContent,
            'is_edited' => true,
        ]);
    }
}
