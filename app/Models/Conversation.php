<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Represents a chat conversation.
 *
 * @property int $id
 * @property string $type Can be 'private' or 'group'.
 * @property string|null $name The name of the conversation, used for group chats.
 * @property int|null $created_by The user who created the group (null for private/legacy conversations).
 * @property bool $is_public True only for the single, app-wide public chat ("Tán gẫu linh tinh").
 * @property string|null $avatar_url Group avatar storage path (groups only).
 * @property string|null $invite_token Active invite-link token for this group, if any.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message[] $messages
 * @property-read \App\Models\Message|null $latestMessage
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AuthAccount[] $participants
 * @property-read \App\Models\AuthAccount|null $creator
 */
class Conversation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_conversations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'name',
        'created_by',
        'is_public',
        'avatar_url',
        'invite_token',
        'background_content_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all messages in the conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message in the conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * The user who created this conversation (groups only).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(AuthAccount::class, 'created_by');
    }

    /**
     * The currently-selected chat background image, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function backgroundContent()
    {
        return $this->belongsTo(UserContent::class, 'background_content_id');
    }

    /**
     * Every background image this conversation has used, most recently used first.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function backgroundHistory(): HasMany
    {
        return $this->hasMany(ConversationBackgroundHistory::class, 'conversation_id')
            ->with('content')
            ->orderByDesc('used_at');
    }

    /**
     * The participants that belong to the conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(AuthAccount::class, 'cyo_conversation_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_at', 'role')
            ->withTimestamps();
    }

    /**
     * Check if a user is a participant in the conversation.
     *
     * @param  int  $userId
     * @return bool
     */
    public function hasParticipant($userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user is the owner of this group conversation.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isOwner($userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        return $participant && $participant->pivot->role === 'owner';
    }

    /**
     * Check if a user is a deputy (phó nhóm) of this group conversation.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isDeputy($userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        return $participant && $participant->pivot->role === 'deputy';
    }

    /**
     * Check if a user can manage this group (owner or deputy) — i.e. kick members,
     * assign/unassign deputies. Renaming/changing the avatar/adding members is open
     * to every participant, so those don't use this check.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isManager($userId): bool
    {
        return $this->isOwner($userId) || $this->isDeputy($userId);
    }

    /**
     * A user may access this conversation without being a participant only if it's the
     * single app-wide public chat — everyone can read/post there.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isAccessibleBy($userId): bool
    {
        return $this->is_public || $this->hasParticipant($userId);
    }

    /**
     * Get the number of unread messages for a user.
     *
     * @param  int  $userId
     * @return int
     */
    public function unreadMessagesCount($userId): int
    {
        $participant = $this->participants()
            ->where('user_id', $userId)
            ->first();

        if (!$participant) {
            return 0;
        }

        $lastRead = $participant->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->when($lastRead, function ($query) use ($lastRead) {
                return $query->where('created_at', '>', $lastRead);
            })
            ->count();
    }
}
