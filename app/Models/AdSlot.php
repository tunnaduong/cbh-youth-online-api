<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A direct-sold ad creative shown on web or mobile at a placement
 * (sidebar | post-bottom | feed-inline | mobile-home-header). Managed from web /admin/ads.
 */
class AdSlot extends Model
{
  protected $table = 'cyo_ad_slots';

  public const PLACEMENTS = ['sidebar', 'post-bottom', 'feed-inline', 'mobile-home-header'];

  protected $fillable = [
    'name', 'placement', 'image_url', 'link_url', 'alt_text',
    'is_active', 'priority', 'starts_at', 'ends_at',
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'priority' => 'integer',
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
  ];

  public function scopeLive($query)
  {
    $now = now();
    return $query->where('is_active', true)
      ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
      ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
  }
}
