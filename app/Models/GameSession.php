<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One play session of a game by a user - tracks how long they played so
 * "most played" ranking and XP payout can be computed from it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int $duration_seconds
 * @property int $xp_earned
 */
class GameSession extends Model
{
  protected $table = 'cyo_game_sessions';

  protected $fillable = [
    'user_id',
    'game_id',
    'started_at',
    'ended_at',
    'duration_seconds',
    'xp_earned',
  ];

  protected $casts = [
    'started_at' => 'datetime',
    'ended_at' => 'datetime',
  ];

  public function game(): BelongsTo
  {
    return $this->belongsTo(Game::class);
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(AuthAccount::class, 'user_id');
  }
}
