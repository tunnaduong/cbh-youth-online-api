<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a study material/document.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $file_path UserContent id
 * @property int $price Points required to purchase
 * @property bool $is_free
 * @property string|null $preview_content
 * @property string|null $preview_path
 * @property int $download_count
 * @property int $view_count
 * @property string $status draft|published
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class StudyMaterial extends Model
{
  use HasFactory, SoftDeletes;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_study_materials';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'title',
    'description',
    'category_id',
    'file_path',
    'price',
    'is_free',
    'preview_content',
    'preview_path',
    'status',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'is_free' => 'boolean',
    'price' => 'integer',
    'download_count' => 'integer',
    'view_count' => 'integer',
  ];

  /**
   * Get the user that owns the study material.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the category of the study material.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function category()
  {
    return $this->belongsTo(StudyMaterialCategory::class, 'category_id');
  }

  /**
   * Get the file content.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function file()
  {
    return $this->belongsTo(UserContent::class, 'file_path');
  }

  /**
   * Get the purchases of this study material.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function purchases()
  {
    return $this->hasMany(StudyMaterialPurchase::class, 'study_material_id');
  }

  /**
   * Get the ratings of this study material.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function ratings()
  {
    return $this->hasMany(StudyMaterialRating::class, 'study_material_id');
  }

  /**
   * Check if a user has purchased this material.
   *
   * @param int $userId
   * @return bool
   */
  public function isPurchasedBy($userId)
  {
    return $this->purchases()->where('user_id', $userId)->exists();
  }

  /**
   * Get average rating.
   * Note: This is a computed attribute, not stored in database
   *
   * @return float
   */
  public function getAverageRatingAttribute()
  {
    $avg = $this->ratings()->avg('rating');
    return $avg ? round((float) $avg, 1) : 0;
  }

  /**
   * Get total ratings count.
   * Note: This is a computed attribute, not stored in database
   *
   * @return int
   */
  public function getRatingsCountAttribute()
  {
    return $this->ratings()->count();
  }
}
