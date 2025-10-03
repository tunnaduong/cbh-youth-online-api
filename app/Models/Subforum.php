<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a subforum within a forum category.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $category_id
 * @property int $order
 * @property bool $is_active
 * @property int|null $moderator_id
 * @property int|null $last_post_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ForumCategory $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Topic[] $posts
 * @property-read \App\Models\User|null $moderator
 * @property-read \App\Models\Topic|null $lastPost
 * @property-read \App\Models\Topic|null $latestTopic
 */
class Subforum extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cyo_forum_subforums';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'order',
        'is_active',
        'moderator_id',
        'last_post_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Get the category that the subforum belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    /**
     * Get the posts for the subforum.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts()
    {
        return $this->hasMany(Topic::class, 'subforum_id');
    }

    /**
     * Get the moderator for the subforum.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * Get the last post for the subforum.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lastPost()
    {
        return $this->belongsTo(Topic::class, 'last_post_id');
    }

    /**
     * Get the latest topic, applying visibility scopes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestTopic()
    {
        return $this->hasOne(Topic::class, 'subforum_id')
            ->ofMany(['created_at' => 'max'], function ($query) {
                $query->visibleToCurrentUser();
            });
    }

    /**
     * Scope a query to only include active subforums.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order subforums by their order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
