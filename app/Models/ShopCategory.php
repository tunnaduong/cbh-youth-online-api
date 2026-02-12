<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopCategory extends Model
{
  use HasFactory;

  protected $table = 'cyo_shop_categories';

  protected $fillable = ['name', 'slug', 'description'];

  public function products()
  {
    return $this->hasMany(ShopProduct::class, 'category_id');
  }
}
