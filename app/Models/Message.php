<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single message within a conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property string|null $content
 * @property string $type
 * @property string|null $file_url
 * @property bool $is_edited
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Conversation $conversation
 * @property-read \App\Models\AuthAccount $user
 */
class Message extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'guest_name',
        'content',
        'type',
        'file_url',
        'is_edited',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_edited' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent this message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Mark the message as read.
     *
     * @return void
     */
    public function markAsRead()
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Check if the message has been read.
     *
     * @return bool
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Edit the message content.
     *
     * @param  string  $newContent
     * @return void
     */
    public function edit(string $newContent)
    {
        $this->update([
            'content' => $newContent,
            'is_edited' => true,
        ]);
    }
}
