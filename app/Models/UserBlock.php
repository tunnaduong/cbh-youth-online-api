<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a user blocking another user.
 *
 * @property int $id
 * @property int $user_id
 * @property int $blocked_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthAccount $user
 * @property-read \App\Models\AuthAccount $blockedUser
 */
class UserBlock extends Model
{
  use HasFactory;

  protected $table = 'cyo_user_blocks';

  protected $fillable = [
    'user_id',
    'blocked_user_id',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }

  public function blockedUser()
  {
    return $this->belongsTo(AuthAccount::class, 'blocked_user_id');
  }
}
