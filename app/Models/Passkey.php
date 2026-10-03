<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A WebAuthn credential (passkey) the account can log in with.
 */
class Passkey extends Model
{
  protected $table = 'cyo_passkeys';

  protected $fillable = ['user_id', 'credential_id', 'credential_hash', 'public_key', 'sign_count', 'name', 'last_used_at'];

  protected $hidden = ['credential_id', 'credential_hash', 'public_key', 'sign_count'];

  protected $casts = [
    'sign_count' => 'integer',
    'last_used_at' => 'datetime',
  ];
}
