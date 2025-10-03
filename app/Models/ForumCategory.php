<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a main category in the forum.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $arrange
 * @property string|null $role_restriction
 * @property string|null $background_image
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ForumSubforum[] $subforums
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Topic[] $topics
 */
class ForumCategory extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_forum_main_categories';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'slug',
    'description',
    'arrange',
    'role_restriction',
    'background_image'
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'arrange' => 'integer'
  ];

  /**
   * Get the subforums for the category.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function subforums()
  {
    return $this->hasMany(ForumSubforum::class, 'main_category_id');
  }

  /**
   * Get all of the topics for the category.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
   */
  public function topics()
  {
    return $this->hasManyThrough(
      Topic::class,
      ForumSubforum::class,
      'main_category_id', // Foreign key on subforums table
      'subforum_id', // Foreign key on topics table
      'id', // Local key on categories table
      'id' // Local key on subforums table
    );
  }

  /**
   * Scope a query to only include active categories.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeActive($query)
  {
    return $query->where('is_active', true);
  }

  /**
   * Scope a query to order categories by their arrangement.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeOrdered($query)
  {
    return $query->orderBy('arrange');
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
