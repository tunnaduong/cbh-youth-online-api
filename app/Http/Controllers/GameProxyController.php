<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameSave;
use App\Services\GameProxyToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Laravel half of the game proxy: it signs the play link, and keeps the
 * cookies / localStorage the proxy captures, per account and game. The proxy
 * itself (streaming, ad blocking) is the Go server in game-proxy/.
 */
class GameProxyController extends Controller
{
  /** Is this game opened through the proxy right now? */
  public static function proxied(Game $game): bool
  {
    return (bool) $game->use_proxy
      && config('gameproxy.enabled')
      && config('gameproxy.public_url') !== ''
      && config('gameproxy.secret') !== '';
  }

  /**
   * POST /games/{slug}/proxy-link - the address to put in the iframe.
   * Without a proxy for this game: { enabled: false } and the client keeps
   * using the game's own iframe_url.
   */
  public function link(Request $request, string $slug): JsonResponse
  {
    $game = Game::where('slug', $slug)->where('is_active', true)->firstOrFail();

    if (!self::proxied($game)) {
      return response()->json(['enabled' => false]);
    }

    $ttl = (int) config('gameproxy.link_ttl');
    $token = GameProxyToken::make(Auth::id(), $game->slug, $game->iframe_url, $ttl);

    return response()->json([
      'enabled' => true,
      'url' => config('gameproxy.public_url') . '/play?t=' . $token,
      'expires_in' => $ttl,
    ]);
  }

  /** GET /games/{slug}/save - what is stored for the signed-in account. */
  public function show(string $slug): JsonResponse
  {
    $game = Game::where('slug', $slug)->firstOrFail();
    $save = GameSave::where('user_id', Auth::id())->where('game_id', $game->id)->first();

    return response()->json([
      'has_save' => (bool) $save,
      'size_bytes' => $save?->size_bytes ?? 0,
      'updated_at' => $save?->updated_at,
    ]);
  }

  /** DELETE /games/{slug}/save - start this game from scratch on every device. */
  public function destroy(string $slug): JsonResponse
  {
    $game = Game::where('slug', $slug)->firstOrFail();
    GameSave::where('user_id', Auth::id())->where('game_id', $game->id)->delete();

    return response()->json(['message' => 'ok']);
  }

  /** GET /internal/game-proxy/saves/{userId}/{slug} - called by the proxy. */
  public function internalShow(int $userId, string $slug): JsonResponse
  {
    $game = Game::where('slug', $slug)->firstOrFail();
    $save = GameSave::where('user_id', $userId)->where('game_id', $game->id)->first();

    return response()->json([
      'cookies' => $save?->cookiesArray() ?? [],
      'storage' => (object) ($save?->storageArray() ?? []),
      'updated_at' => $save?->updated_at?->getTimestamp() ?? 0,
    ]);
  }

  /** PUT /internal/game-proxy/saves/{userId}/{slug} - called by the proxy. */
  public function internalUpdate(Request $request, int $userId, string $slug): JsonResponse
  {
    $game = Game::where('slug', $slug)->firstOrFail();

    $data = $request->validate([
      'cookies' => ['nullable', 'array', 'max:80'],
      'cookies.*.name' => ['required', 'string', 'max:256'],
      'cookies.*.value' => ['present', 'nullable', 'string', 'max:8192'],
      'storage' => ['nullable', 'array', 'max:2000'],
    ]);

    $cookies = array_values($data['cookies'] ?? []);
    $storage = [];
    foreach (($data['storage'] ?? []) as $key => $value) {
      // localStorage holds strings only.
      if (is_string($key) && (is_string($value) || $value === null)) {
        $storage[$key] = (string) $value;
      }
    }

    $cookiesJson = json_encode($cookies, JSON_UNESCAPED_UNICODE);
    $storageJson = json_encode((object) $storage, JSON_UNESCAPED_UNICODE);
    $size = strlen($cookiesJson) + strlen($storageJson);

    if ($size > (int) config('gameproxy.max_save_bytes')) {
      return response()->json(['message' => 'Save too large.'], 413);
    }

    $save = GameSave::updateOrCreate(
      ['user_id' => $userId, 'game_id' => $game->id],
      ['cookies' => $cookiesJson, 'storage' => $storageJson, 'size_bytes' => $size]
    );

    return response()->json(['updated_at' => $save->updated_at->getTimestamp()]);
  }
}
