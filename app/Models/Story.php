<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cyo_stories';

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
        'duration'
    ];

    protected $casts = [
        'text_position' => 'array',
        'expires_at' => 'datetime',
        'duration' => 'integer'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    /**
     * Get the user that owns the story
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    /**
     * Get the viewers for the story
     */
    public function viewers(): HasMany
    {
        return $this->hasMany(StoryViewer::class, 'story_id');
    }

    /**
     * Get the reactions for the story
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(StoryReaction::class, 'story_id');
    }

    /**
     * Check if story has expired
     */
    public function hasExpired(): bool
    {
        return $this->expires_at ? $this->expires_at->isPast() : false;
    }

    /**
     * Scope a query to only include active stories
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
