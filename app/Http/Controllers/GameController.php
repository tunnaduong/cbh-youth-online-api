<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
  private function formatGame(Game $game): array
  {
    return [
      'id' => $game->id,
      'name' => $game->name,
      'slug' => $game->slug,
      'description' => $game->description,
      'category' => $game->category,
      'image_url' => $game->image_url,
      'platform' => $game->platform,
    ];
  }

  /**
   * "Most played" score = total minutes played across all sessions this
   * period * distinct player count for that period - a game a few people
   * play for hours and a game hundreds of people briefly try can both
   * surface, rather than pure playtime or pure headcount alone.
   */
  private function mostPlayedQuery($sinceDays = 7)
  {
    return GameSession::query()
      ->where('created_at', '>=', now()->subDays($sinceDays))
      ->select('game_id')
      ->selectRaw('SUM(duration_seconds) as total_seconds')
      ->selectRaw('COUNT(DISTINCT user_id) as player_count')
      ->selectRaw('SUM(duration_seconds) * COUNT(DISTINCT user_id) as score')
      ->groupBy('game_id')
      ->orderByDesc('score');
  }

  /**
   * List games: categories, most-played, newest, and (optionally) filtered
   * by category/search query.
   */
  public function index(Request $request)
  {
    $query = Game::where('is_active', true);

    if ($request->filled('category') && $request->category !== 'all') {
      $query->where('category', $request->category);
    }

    if ($request->filled('q')) {
      $search = $request->q;
      $query->where('name', 'like', "%{$search}%");
    }

    $games = $query->orderBy('sort_order')->orderByDesc('id')->get();

    $mostPlayedStats = $this->mostPlayedQuery()->limit(10)->get()->keyBy('game_id');
    $mostPlayed = Game::where('is_active', true)
      ->whereIn('id', $mostPlayedStats->keys())
      ->get()
      ->map(function ($game) use ($mostPlayedStats) {
        $stats = $mostPlayedStats[$game->id];
        return [
          ...$this->formatGame($game),
          'play_count' => (int) $stats->player_count,
          'total_minutes' => (int) floor($stats->total_seconds / 60),
        ];
      })
      ->sortByDesc(fn($g) => $g['play_count'] * $g['total_minutes'])
      ->values();

    $newest = Game::where('is_active', true)
      ->orderByDesc('created_at')
      ->limit(10)
      ->get()
      ->map(fn($g) => $this->formatGame($g));

    return response()->json([
      'games' => $games->map(fn($g) => $this->formatGame($g)),
      'most_played' => $mostPlayed,
      'newest' => $newest,
      'categories' => Game::where('is_active', true)
        ->select('category')
        ->distinct()
        ->pluck('category'),
    ]);
  }

  public function show($slug)
  {
    $game = Game::where('slug', $slug)->where('is_active', true)->firstOrFail();

    return response()->json([
      ...$this->formatGame($game),
      'iframe_url' => $game->iframe_url,
    ]);
  }

  public function random(Request $request)
  {
    $query = Game::where('is_active', true);

    if ($request->filled('platform') && in_array($request->platform, ['pc', 'mobile'])) {
      $query->where(function ($q) use ($request) {
        $q->where('platform', 'both')->orWhere('platform', $request->platform);
      });
    }

    $game = $query->inRandomOrder()->first();

    if (!$game) {
      return response()->json(['message' => 'Không có game nào khả dụng.'], 404);
    }

    return response()->json($this->formatGame($game));
  }

  /**
   * Start a play session. Called right when the iframe is shown.
   */
  public function startSession(Request $request, $slug)
  {
    $game = Game::where('slug', $slug)->where('is_active', true)->firstOrFail();
    $user = Auth::user();

    $session = GameSession::create([
      'user_id' => $user->id,
      'game_id' => $game->id,
      'started_at' => now(),
    ]);

    return response()->json(['session_id' => $session->id]);
  }

  /**
   * Heartbeat/end shared logic: recompute duration server-side from
   * started_at (never trust a client-supplied elapsed value), and award XP
   * for any newly-completed minute since the last heartbeat.
   */
  private function syncSession(GameSession $session, bool $closing): array
  {
    $now = now();
    $newDuration = max($session->duration_seconds, $session->started_at->diffInSeconds($now));
    $previousMinutes = intdiv($session->duration_seconds, 60);
    $newMinutes = intdiv($newDuration, 60);
    $xpToAward = max(0, $newMinutes - $previousMinutes);

    $session->duration_seconds = $newDuration;
    $session->xp_earned += $xpToAward;
    if ($closing) {
      $session->ended_at = $now;
    }
    $session->save();

    if ($xpToAward > 0) {
      PointsService::onGamePlayed($session->user_id, $xpToAward, $session->id);
    }

    return [
      'duration_seconds' => $session->duration_seconds,
      'xp_earned' => $session->xp_earned,
      'xp_awarded_this_call' => $xpToAward,
    ];
  }

  public function heartbeat(Request $request, $sessionId)
  {
    $session = GameSession::where('id', $sessionId)
      ->where('user_id', Auth::id())
      ->whereNull('ended_at')
      ->firstOrFail();

    return response()->json($this->syncSession($session, false));
  }

  public function endSession(Request $request, $sessionId)
  {
    $session = GameSession::where('id', $sessionId)
      ->where('user_id', Auth::id())
      ->whereNull('ended_at')
      ->firstOrFail();

    return response()->json($this->syncSession($session, true));
  }

  /**
   * Game-specific leaderboard - total XP earned from playing games only
   * (separate from the site-wide forum points ranking).
   */
  public function leaderboard(Request $request)
  {
    $period = $request->input('period', 'week'); // week|all
    $query = DB::table('cyo_game_sessions')
      ->select('user_id')
      ->selectRaw('SUM(xp_earned) as total_xp')
      ->groupBy('user_id')
      ->orderByDesc('total_xp')
      ->limit(20);

    if ($period === 'week') {
      $query->where('created_at', '>=', now()->subWeek());
    }

    $rows = $query->get();

    $users = AuthAccount::whereIn('id', $rows->pluck('user_id'))
      ->with('profile')
      ->get()
      ->keyBy('id');

    $leaderboard = $rows
      ->filter(fn($row) => isset($users[$row->user_id]))
      ->map(function ($row) use ($users) {
        $user = $users[$row->user_id];
        return [
          'id' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? $user->username,
          'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
          'xp' => (int) $row->total_xp,
        ];
      })
      ->values();

    return response()->json(['leaderboard' => $leaderboard]);
  }
}
