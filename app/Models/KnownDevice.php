<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A device an account has logged in from before - used to tell a login on
 * a new device (which the owner gets an email about) from a familiar one.
 */
class KnownDevice extends Model
{
  protected $table = 'cyo_known_devices';

  protected $fillable = ['user_id', 'fingerprint', 'platform', 'device_name', 'device_model', 'last_login_at'];

  protected $casts = [
    'last_login_at' => 'datetime',
  ];
}
