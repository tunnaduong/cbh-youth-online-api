<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a purchase of a study material.
 *
 * @property int $id
 * @property int $user_id
 * @property int $study_material_id
 * @property int $price_paid Points paid at time of purchase
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StudyMaterialPurchase extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_study_material_purchases';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'study_material_id',
    'price_paid',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'price_paid' => 'integer',
  ];

  /**
   * Get the user who made the purchase.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  /**
   * Get the study material that was purchased.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function studyMaterial()
  {
    return $this->belongsTo(StudyMaterial::class, 'study_material_id');
  }
}

