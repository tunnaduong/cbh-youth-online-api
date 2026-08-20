<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
  use HasFactory;

  protected $table = 'cyo_shop_orders';

  protected $fillable = [
    'user_id', 'total_amount', 'status',
    'shipping_address', 'phone', 'note',
    'payment_method', 'payment_status', 'payment_code', 'paid_at',
  ];

  protected $casts = [
    'total_amount' => 'integer',
    'paid_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function items()
  {
    return $this->hasMany(ShopOrderItem::class, 'order_id');
  }
}
