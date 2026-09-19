<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductVariant extends Model
{
  protected $table = 'cyo_shop_product_variants';

  protected $fillable = ['product_id', 'options', 'sku', 'price', 'stock', 'image_url'];

  protected $casts = [
    'options' => 'array',
    'price' => 'integer',
    'stock' => 'integer',
  ];

  public function product()
  {
    return $this->belongsTo(ShopProduct::class, 'product_id');
  }

  /** "Size: M / Màu: Đen", in the product's option-group order (MySQL JSON doesn't keep key order). */
  public function label(): string
  {
    $values = $this->options ?? [];
    $order = collect($this->product?->options ?? [])->pluck('name')->all();
    $names = array_values(array_unique([...array_intersect($order, array_keys($values)), ...array_keys($values)]));
    return collect($names)->map(fn($k) => "{$k}: {$values[$k]}")->implode(' / ');
  }
}
