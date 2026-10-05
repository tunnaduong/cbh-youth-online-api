<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
  use HasFactory;

  protected $table = 'cyo_shop_orders';

  protected $fillable = [
    'user_id', 'total_amount', 'discount_percent', 'status',
    'shipping_address', 'shipping_lat', 'shipping_lng', 'phone', 'note',
    'payment_method', 'payment_status', 'payment_code', 'paid_at',
  ];

  protected $casts = [
    'total_amount' => 'integer',
    'paid_at' => 'datetime',
    'shipping_lat' => 'float',
    'shipping_lng' => 'float',
  ];

  // Sent with every order, so each admin screen can link straight to the map.
  protected $appends = ['maps_url'];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function items()
  {
    return $this->hasMany(ShopOrderItem::class, 'order_id');
  }

  /**
   * A Google Maps link to the pin the customer dropped at checkout, or null
   * for an order without one (placed from the chat, or before the map
   * picker existed).
   */
  public function getMapsUrlAttribute(): ?string
  {
    $lat = $this->attributes['shipping_lat'] ?? null;
    $lng = $this->attributes['shipping_lng'] ?? null;

    if ($lat === null || $lng === null) {
      return null;
    }

    return 'https://www.google.com/maps?q=' . (float) $lat . ',' . (float) $lng;
  }
}
