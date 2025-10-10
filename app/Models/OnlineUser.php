<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineUser extends Model
{
  protected $table = 'cyo_online_users';
  protected $fillable = [
    'session_id',
    'user_id',
    'is_hidden',
    'last_activity',
    'ip_address',
    'user_agent',
  ];
  public $timestamps = false;
}
