<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of an account's gift shop cart (variant_id 0 = no variant).
 */
class ShopCartItem extends Model
{
  protected $table = 'cyo_shop_cart_items';

  protected $fillable = ['user_id', 'product_id', 'variant_id', 'quantity'];

  protected $casts = [
    'variant_id' => 'integer',
    'quantity' => 'integer',
  ];
}
