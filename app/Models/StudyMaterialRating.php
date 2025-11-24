<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a rating/review of a study material.
 *
 * @property int $id
 * @property int $user_id
 * @property int $study_material_id
 * @property int $rating 1-5 stars
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StudyMaterialRating extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_study_material_ratings';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'study_material_id',
    'rating',
    'comment',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'rating' => 'integer',
  ];

  /**
   * Get the user who made the rating.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the study material that was rated.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function studyMaterial()
  {
    return $this->belongsTo(StudyMaterial::class, 'study_material_id');
  }
}

