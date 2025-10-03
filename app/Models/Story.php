<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a user's story.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $content
 * @property string $media_type
 * @property string|null $media_url
 * @property string|null $background_color
 * @property string|null $font_style
 * @property array|null $text_position
 * @property string $privacy
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $duration
 * @property bool $pinned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StoryViewer[] $viewers
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StoryReaction[] $reactions
 */
class Story extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_stories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'content',
        'media_type',
        'media_url',
        'background_color',
        'font_style',
        'text_position',
        'privacy',
        'expires_at',
        'duration',
        'pinned'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'text_position' => 'array',
        'expires_at' => 'datetime',
        'duration' => 'integer',
        'pinned' => 'boolean'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at'
    ];

    /**
     * Get the user that owns the story.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Get the viewers for the story.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function viewers(): HasMany
    {
        return $this->hasMany(StoryViewer::class, 'story_id');
    }

    /**
     * Get the reactions for the story.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(StoryReaction::class, 'story_id');
    }

    /**
     * Check if the story has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return $this->expires_at ? $this->expires_at->isPast() : false;
    }

    /**
     * Scope a query to only include active (non-expired) stories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope a query to only include pinned stories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePinned($query)
    {
        return $query->where('pinned', true);
    }
}
