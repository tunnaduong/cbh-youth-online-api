<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineRecord extends Model
{
  protected $table = 'cyo_online_record';
  protected $fillable = ['id', 'max_online', 'recorded_at'];
  public $timestamps = false;
}
