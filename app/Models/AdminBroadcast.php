<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A push notification broadcast sent by an admin from the admin panel.
 */
class AdminBroadcast extends Model
{
  protected $table = 'cyo_admin_broadcasts';

  protected $fillable = [
    'admin_id', 'title', 'body', 'url', 'topic_id', 'audience', 'audience_value', 'channels',
    'save_to_inbox', 'status', 'recipients_count', 'web_sent', 'mobile_sent', 'error', 'sent_at',
  ];

  protected $casts = [
    'audience_value' => 'array',
    'channels' => 'array',
    'save_to_inbox' => 'boolean',
    'sent_at' => 'datetime',
  ];

  public function admin()
  {
    return $this->belongsTo(AuthAccount::class, 'admin_id');
  }

  /**
   * Query of the recipient account ids for this broadcast.
   */
  public static function recipientsQuery(string $audience, $value = null)
  {
    $query = AuthAccount::query()->select('id');

    if ($audience === 'role') {
      $query->where('role', $value);
    } elseif ($audience === 'users') {
      $query->whereIn('id', (array) $value);
    }

    // Banned accounts don't get broadcasts.
    return $query->where(fn($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()));
  }
}
