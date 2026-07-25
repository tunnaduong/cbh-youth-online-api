<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a hashtag that can be attached to topics.
 *
 * @property int $id
 * @property string $tag
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Topic[] $topics
 */
class Hashtag extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_hashtags';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tag',
    ];

    /**
     * The topics that use this hashtag.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'cyo_topic_hashtags', 'hashtag_id', 'topic_id')->withTimestamps();
    }
}
