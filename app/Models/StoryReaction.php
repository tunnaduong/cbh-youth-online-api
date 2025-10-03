<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a reaction to a story.
 *
 * @property int $id
 * @property int $story_id
 * @property int $user_id
 * @property string $reaction_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Story $story
 * @property-read \App\Models\AuthAccount $user
 */
class StoryReaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_story_reactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'story_id',
        'user_id',
        'reaction_type'
    ];

    /**
     * Get the story that was reacted to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    /**
     * Get the user who reacted to the story.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
