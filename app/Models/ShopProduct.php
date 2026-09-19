<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProduct extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'cyo_shop_products';

  protected $fillable = [
    'name', 'slug', 'sku', 'description', 'price',
    'stock', 'image_url', 'options', 'category_id', 'is_active'
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'price' => 'integer',
    'stock' => 'integer',
    'options' => 'array',
  ];

  public function variants()
  {
    return $this->hasMany(ShopProductVariant::class, 'product_id');
  }

  public function category()
  {
    return $this->belongsTo(ShopCategory::class, 'category_id');
  }
}
