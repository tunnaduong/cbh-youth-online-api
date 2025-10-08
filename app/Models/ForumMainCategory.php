<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a main category in the forum.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ForumSubforum[] $subforums
 */
class ForumMainCategory extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = "cyo_forum_main_categories";

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = ['name', 'description', 'seo_description', 'background_image'];

  /**
   * Get the subforums for the main category.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function subforums()
  {
    return $this->hasMany(ForumSubforum::class, 'main_category_id');
  }
}
