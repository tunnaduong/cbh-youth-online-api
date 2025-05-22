<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryViewer extends Model
{
    use HasFactory;

    protected $table = 'cyo_story_viewers';

    protected $fillable = [
        'story_id',
        'user_id',
        'viewed_at'
    ];

    protected $casts = [
        'viewed_at' => 'datetime'
    ];

    /**
     * Get the story that was viewed
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    /**
     * Get the user who viewed the story
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
