<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the game proxy captured for one account in one game: cookies and
 * localStorage, so the game continues on another device.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property string|null $cookies JSON: [{name, value}]
 * @property string|null $storage JSON: {key: value}
 * @property int $size_bytes
 */
class GameSave extends Model
{
  protected $table = 'cyo_game_saves';

  protected $fillable = ['user_id', 'game_id', 'cookies', 'storage', 'size_bytes'];

  public function game(): BelongsTo
  {
    return $this->belongsTo(Game::class);
  }

  public function cookiesArray(): array
  {
    return $this->cookies ? (json_decode($this->cookies, true) ?: []) : [];
  }

  public function storageArray(): array
  {
    return $this->storage ? (json_decode($this->storage, true) ?: []) : [];
  }
}
