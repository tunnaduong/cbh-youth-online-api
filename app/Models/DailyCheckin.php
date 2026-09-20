<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCheckin extends Model
{
  protected $table = 'cyo_daily_checkins';

  protected $fillable = [
    'user_id',
    'checkin_date',
    'points_awarded',
    'streak_day',
  ];

  protected $casts = [
    'checkin_date' => 'date',
    'points_awarded' => 'integer',
    'streak_day' => 'integer',
  ];

  public function user()
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}
