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

    // Get real client IP address, handling proxies properly
    $ip = $request->ip();

    // If we get localhost IPs, try to get the real IP from headers
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
      $ip = $request->header('X-Forwarded-For')
        ?? $request->header('X-Real-IP')
        ?? $request->header('CF-Connecting-IP')
        ?? $request->header('X-Forwarded-For')
        ?? $ip;

      // If X-Forwarded-For contains multiple IPs, get the first one
      if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
      }
    }

    $now = now();

    // Cleanup ngay lập tức để tránh tích lũy records
    $this->performCleanup($now, $userId, $ip, $sessionId);

    // Kiểm tra xem record đã tồn tại chưa trước khi insert
    // Đối với user đã đăng nhập: tìm theo user_id và session_id
    // Đối với guest: tìm theo session_id và ip_address
    $existingRecord = null;

    if ($userId) {
      // User đã đăng nhập: tìm theo user_id và session_id
      $existingRecord = DB::table('cyo_online_users')
        ->where('user_id', $userId)
        ->where('session_id', $sessionId)
        ->first();
    } else {
      // Guest: tìm theo session_id và ip_address
      $existingRecord = DB::table('cyo_online_users')
        ->whereNull('user_id')
        ->where('session_id', $sessionId)
        ->where('ip_address', $ip)
        ->first();
    }

    if ($existingRecord) {
      // Chỉ update last_activity nếu record đã tồn tại
      DB::table('cyo_online_users')
        ->where('id', $existingRecord->id)
        ->update([
          'last_activity' => $now,
          'ip_address' => $ip,
        ]);
    } else {
      // Chỉ insert nếu chưa có record
      DB::table('cyo_online_users')->insert([
        'user_id' => $userId,
        'session_id' => $sessionId,
        'last_activity' => $now,
        'ip_address' => $ip,
        'is_hidden' => 0,
      ]);
    }

    // Update max online mỗi 2 phút (no caching)
    $lastMaxUpdate = DB::table('cyo_online_record')
      ->where('id', 1)
      ->value('recorded_at');

    if (!$lastMaxUpdate || now()->diffInMinutes($lastMaxUpdate) >= 2) {
      \App\Http\Controllers\ForumController::updateMaxOnline();
    }

    return $next($request);
  }

  private function performCleanup($now, $userId, $ip, $sessionId)
  {
    // Delete records older than 15 minutes (less aggressive cleanup)
    DB::table('cyo_online_users')
      ->where('last_activity', '<', $now->subMinutes(15))
      ->delete();

    // Delete duplicate guest records from the same IP but different session_id
    // Chỉ xóa nếu là guest (không đăng nhập) và có cùng IP nhưng khác session
    if (!$userId) {
      DB::table('cyo_online_users')
        ->whereNull('user_id')
        ->where('ip_address', $ip)
        ->where('session_id', '<>', $sessionId)
        ->delete();
    }

    // Đối với user đã đăng nhập, xóa các record cũ của cùng user nhưng khác session
    // (trường hợp user đăng nhập từ nhiều thiết bị/trình duyệt khác nhau)
    if ($userId) {
      DB::table('cyo_online_users')
        ->where('user_id', $userId)
        ->where('session_id', '<>', $sessionId)
        ->where('last_activity', '<', $now->subMinutes(10))
        ->delete();
    }
  }
}
