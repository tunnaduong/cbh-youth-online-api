<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a category for study materials.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StudyMaterialCategory extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'cyo_study_material_categories';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'description',
    'slug',
    'order',
  ];

  /**
   * Get the study materials in this category.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function studyMaterials()
  {
    return $this->hasMany(StudyMaterial::class, 'category_id');
  }
}

