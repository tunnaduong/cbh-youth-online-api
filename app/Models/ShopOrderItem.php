<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrderItem extends Model
{
  use HasFactory;

  protected $table = 'cyo_shop_order_items';

  protected $fillable = [
    'order_id', 'product_id', 'variant_id', 'variant_label', 'quantity', 'price'
  ];

  public function order()
  {
    return $this->belongsTo(ShopOrder::class, 'order_id');
  }

  public function variant()
  {
    return $this->belongsTo(ShopProductVariant::class, 'variant_id');
  }

  public function product()
  {
    return $this->belongsTo(ShopProduct::class, 'product_id');
  }

  /** Return this line's quantity to stock (order cancelled). */
  public function restock(): void
  {
    ShopProduct::withTrashed()->where('id', $this->product_id)->increment('stock', $this->quantity);
    if ($this->variant_id) {
      ShopProductVariant::where('id', $this->variant_id)->increment('stock', $this->quantity);
    }
  }
}
