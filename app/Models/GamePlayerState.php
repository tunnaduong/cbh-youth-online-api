<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one player's game state looks like on the server, so it follows the
 * player to every device:
 *  - cookies: cookies the game's own server set (replayed on later requests)
 *  - storage: the game's localStorage (key => value), handed back to the page
 *    when it loads and saved again when the game changes it.
 *
 * @property int $user_id
 * @property int $game_id
 * @property array $cookies list of {name, value, domain, path, expires, secure}
 * @property array $storage map of key => string value
 */
class GamePlayerState extends Model
{
    protected $table = 'cyo_game_player_states';

    protected $fillable = ['user_id', 'game_id', 'cookies', 'storage'];

    protected $casts = [
        'cookies' => 'encrypted:array',
        'storage' => 'encrypted:array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
