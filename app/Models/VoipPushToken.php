<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An iOS PushKit (VoIP) token of one device, for ringing calls through CallKit.
 *
 * @property int $id
 * @property int $user_id
 * @property string $voip_token
 * @property string|null $apns_environment
 * @property int|null $access_token_id
 * @property bool $is_active
 */
class VoipPushToken extends Model
{
  protected $table = 'cyo_voip_push_tokens';

  protected $fillable = [
    'user_id',
    'voip_token',
    'apns_environment',
    'access_token_id',
    'is_active',
    'last_used_at',
  ];

  protected $casts = [
    'is_active' => 'boolean',
    'last_used_at' => 'datetime',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}
