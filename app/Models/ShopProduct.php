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
    'stock', 'image_url', 'image_urls', 'options', 'category_id', 'is_active'
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'price' => 'integer',
    'stock' => 'integer',
    'options' => 'array',
    'image_urls' => 'array',
  ];

  public function getImageUrlsAttribute($value)
  {
    if ($value) {
      $decoded = is_string($value) ? json_decode($value, true) : $value;
      if (is_array($decoded) && count($decoded) > 0) {
        return array_values($decoded);
      }
    }
    return !empty($this->attributes['image_url']) ? [$this->attributes['image_url']] : [];
  }

  public function variants()
  {
    return $this->hasMany(ShopProductVariant::class, 'product_id');
  }

  public function category()
  {
    return $this->belongsTo(ShopCategory::class, 'category_id');
  }
}
