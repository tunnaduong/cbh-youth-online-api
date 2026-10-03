<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A device that passed the two-factor challenge with "remember this device"
 * ticked, so later logins from it skip the challenge until it expires.
 */
class TwoFactorTrustedDevice extends Model
{
  protected $table = 'cyo_two_factor_trusted_devices';

  protected $fillable = ['user_id', 'token_hash', 'device_name', 'last_used_at', 'expires_at'];

  protected $hidden = ['token_hash'];

  protected $casts = [
    'last_used_at' => 'datetime',
    'expires_at' => 'datetime',
  ];
}
