<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UpdateOnlineUsers
{
  public function handle(Request $request, Closure $next)
  {
    $userId = Auth::id(); // null nếu chưa login
    $sessionId = session()->getId();
    $ip = $request->ip();
    $now = now();

    // Xác định identifier: user_id nếu login, session_id nếu guest
    $identifier = $userId ?? $sessionId;

    // Cập nhật hoặc insert bản ghi online user
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

    // Xóa bản ghi cũ hơn 5 phút
    DB::table('cyo_online_users')
      ->where('last_activity', '<', $now->subMinutes(5))
      ->delete();

    // Xóa các bản ghi guest trùng IP nhưng khác session_id (tránh spam khi reload/hot reload)
    if (!$userId) {
      DB::table('cyo_online_users')
        ->whereNull('user_id')
        ->where('ip_address', $ip)
        ->where('session_id', '<>', $sessionId)
        ->delete();
    }

    // Cập nhật max online
    \App\Http\Controllers\ForumController::updateMaxOnline();

    return $next($request);
  }
}
