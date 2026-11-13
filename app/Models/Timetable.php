<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
  protected $table = 'cyo_timetables';

  protected $fillable = ['user_id', 'data'];

  protected $casts = ['data' => 'array'];
}
