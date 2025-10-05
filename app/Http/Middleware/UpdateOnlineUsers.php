<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to update the online status of users and guests.
 */
class UpdateOnlineUsers
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function handle(Request $request, Closure $next)
  {
    $userId = Auth::id(); // null if not logged in
    $sessionId = session()->getId();
    $ip = $request->ip();
    $now = now();

    // Chỉ chạy cleanup mỗi 30 giây để giảm tải database
    $lastCleanup = cache()->get('last_online_cleanup', 0);
    if (now()->timestamp - $lastCleanup > 30) {
      $this->performCleanup($now, $userId, $ip, $sessionId);
      cache()->put('last_online_cleanup', now()->timestamp, 60);
    }

    // Update or insert the online user record
    DB::table('cyo_online_users')->updateOrInsert(
      [
        'user_id' => $userId,
        'session_id' => $sessionId,
      ],
      [
        'last_activity' => $now,
        'ip_address' => $ip,
        'is_hidden' => 0,
      ]
    );

    // Chỉ update max online mỗi 2 phút
    $lastMaxUpdate = cache()->get('last_max_online_update', 0);
    if (now()->timestamp - $lastMaxUpdate > 120) {
      \App\Http\Controllers\ForumController::updateMaxOnline();
      cache()->put('last_max_online_update', now()->timestamp, 300);
    }

    return $next($request);
  }

  private function performCleanup($now, $userId, $ip, $sessionId)
  {
    // Delete records older than 5 minutes
    DB::table('cyo_online_users')
      ->where('last_activity', '<', $now->subMinutes(5))
      ->delete();

    // Delete duplicate guest records from the same IP but different session_id
    if (!$userId) {
      DB::table('cyo_online_users')
        ->whereNull('user_id')
        ->where('ip_address', $ip)
        ->where('session_id', '<>', $sessionId)
        ->delete();
    }
  }
}
