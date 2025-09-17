<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumCategory extends Model
{
  use HasFactory;

  protected $table = 'cyo_forum_main_categories';

  protected $fillable = [
    'name',
    'slug',
    'description',
    'arrange',
    'role_restriction',
    'background_image'
  ];

  protected $casts = [
    'arrange' => 'integer'
  ];

  public function subforums()
  {
    return $this->hasMany(ForumSubforum::class, 'main_category_id');
  }

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

  // Scope để lấy các danh mục đang hoạt động
  public function scopeActive($query)
  {
    return $query->where('is_active', true);
  }

  // Scope để sắp xếp theo thứ tự
  public function scopeOrdered($query)
  {
    return $query->orderBy('arrange');
  }

  public function getRouteKeyName()
  {
    return 'slug';
  }
}
