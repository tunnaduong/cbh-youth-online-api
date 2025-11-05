<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a subforum within a main forum category.
 *
 * @property int $id
 * @property int $main_category_id
 * @property int|null $moderator_id
 * @property string $name
 * @property string|null $description
 * @property bool $active
 * @property bool $pinned
 * @property string|null $role_restriction
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ForumMainCategory $mainCategory
 * @property-read \App\Models\AuthAccount|null $moderator
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Topic[] $topics
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TopicComment[] $comments
 * @property-read int $posts_count
 * @property-read \App\Models\Topic|null $latest_post
 * @property-read \App\Models\Topic|null $latestVisibleTopic
 * @property-read \App\Models\Topic|null $latestTopic
 * @property-read \App\Models\Topic|null $latestPublicTopic
 */
class ForumSubforum extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = "cyo_forum_subforums";

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = ['main_category_id', 'arrange', 'name', 'description', 'seo_description', 'active', 'pinned', 'role_restriction', 'background_image'];

  /**
   * Get the main category that the subforum belongs to.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function mainCategory()
  {
    return $this->belongsTo(ForumMainCategory::class, 'main_category_id');
  }

  /**
   * Get the moderator for the subforum.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function moderator()
  {
    return $this->belongsTo(AuthAccount::class, 'moderator_id');
  }

  /**
   * Get the topics for the subforum.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function topics()
  {
    return $this->hasMany(Topic::class, 'subforum_id');
  }

  /**
   * Get all of the comments for the subforum.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
   */
  public function comments()
  {
    return $this->hasManyThrough(
      TopicComment::class,
      Topic::class,
      'subforum_id', // Foreign key on topics table
      'topic_id', // Foreign key on comments table
      'id', // Local key on subforums table
      'id' // Local key on topics table
    );
  }

  /**
   * Get the number of posts in the subforum.
   *
   * @return int
   */
  public function getPostsCountAttribute()
  {
    return $this->topics()->count();
  }

  /**
   * Get the latest post in the subforum.
   *
   * @return \App\Models\Topic|null
   */
  public function getLatestPostAttribute()
  {
    // Use orderBy with a fallback to check that 'created_at' is not null
    return $this->topics()->whereNotNull('created_at')->latest('created_at')->first();
  }

  /**
   * Get the latest topic in the subforum that is visible to the current user.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   */
  public function latestVisibleTopic()
  {
    return $this->hasOne(Topic::class, 'subforum_id')
      ->visibleToCurrentUser()
      ->latestOfMany();
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
   * Get the latest public topic.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   */
  public function latestPublicTopic()
  {
    return $this->hasOne(Topic::class, 'subforum_id')
      ->ofMany(['created_at' => 'max'], function ($query) {
        $query->publicOnly();
      });
  }

  /**
   * Get the route key for the model.
   *
   * @return string
   */
  public function getRouteKeyName()
  {
    return 'slug';
  }
}
