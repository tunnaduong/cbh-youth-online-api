<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryReaction extends Model
{
    use HasFactory;

    protected $table = 'cyo_story_reactions';

    protected $fillable = [
        'story_id',
        'user_id',
        'reaction_type'
    ];

    /**
     * Get the story that was reacted to
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    /**
     * Get the user who reacted to the story
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
