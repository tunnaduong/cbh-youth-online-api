<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    protected $table = 'cyo_conversations';

    protected $fillable = [
        'type',
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all messages in the conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message in the conversation
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Get all participants in the conversation
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(AuthAccount::class, 'cyo_conversation_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Check if a user is a participant in the conversation
     */
    public function hasParticipant($userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Get unread messages count for a user
     */
    public function unreadMessagesCount($userId): int
    {
        $lastRead = $this->participants()
            ->where('user_id', $userId)
            ->first()
            ->pivot
            ->last_read_at;

        if (!$lastRead) {
            return $this->messages()->count();
        }

        return $this->messages()
            ->where('created_at', '>', $lastRead)
            ->where('user_id', '!=', $userId)
            ->count();
    }
}
