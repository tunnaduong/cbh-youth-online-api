<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OnlineUser;
use App\Models\OnlineRecord;
use Carbon\Carbon;

class OnlineUserController extends Controller
{
  public function track(Request $request)
  {
    // Get real client IP address, handling proxies properly
    $ip = $request->ip();

    // If we get localhost IPs, try to get the real IP from headers
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
      $ip = $request->header('X-Forwarded-For')
        ?? $request->header('X-Real-IP')
        ?? $request->header('CF-Connecting-IP')
        ?? $ip;

      // If X-Forwarded-For contains multiple IPs, get the first one
      if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
      }
    }

    $now = Carbon::now();
    $userId = auth()->check() ? auth()->id() : null;
    $isHidden = $request->boolean('is_hidden', false);
    $userAgent = substr($request->header('User-Agent', ''), 0, 255);

    // Skip nếu không có header xác nhận từ frontend (Vercel, etc.)
    if (!$request->header('X-From-Frontend')) {
      return response()->json(['message' => 'Skipped: not frontend user']);
    }

    // Cleanup trước để tránh duplicate
    OnlineUser::where('last_activity', '<', Carbon::now()->subMinutes(5))->delete();

    // Nếu có user ID, nhận diện theo user_id (đăng nhập)
    if ($userId) {
      // Xóa tất cả records cũ của user này trước
      OnlineUser::where('user_id', $userId)->delete();

      // Tạo record mới
      OnlineUser::create([
        'user_id' => $userId,
        'last_activity' => $now,
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'is_hidden' => $isHidden,
        'session_id' => session()->getId(),
      ]);
    } else {
      // Nếu chưa đăng nhập, nhận diện theo IP (không cần User-Agent vì có thể thay đổi)
      // Xóa tất cả records cũ của IP này trước
      OnlineUser::where('ip_address', $ip)
        ->whereNull('user_id')
        ->delete();

      // Tạo record mới
      OnlineUser::create([
        'user_id' => null,
        'last_activity' => $now,
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'is_hidden' => $isHidden,
        'session_id' => session()->getId(),
      ]);
    }


    // Cập nhật kỷ lục max online
    $this->updateMaxOnline();

    return response()->json(['message' => 'Online user tracked successfully.']);
  }

  public function getStats()
  {
    $users = OnlineUser::where('last_activity', '>=', Carbon::now()->subMinutes(5))->get();

    $registered = $users->whereNotNull('user_id')->where('is_hidden', false)->count();
    $hidden = $users->whereNotNull('user_id')->where('is_hidden', true)->count();
    $guests = $users->whereNull('user_id')->count();

    return response()->json([
      'total' => $users->count(),
      'registered' => $registered,
      'hidden' => $hidden,
      'guests' => $guests,
    ]);
  }

  public function updateMaxOnline()
  {
    $total = OnlineUser::where('last_activity', '>=', Carbon::now()->subMinutes(5))->count();
    $record = OnlineRecord::first();

    if (!$record) {
      OnlineRecord::create([
        'id' => 1,
        'max_online' => $total,
        'recorded_at' => now(),
      ]);
    } elseif ($total > $record->max_online) {
      $record->update([
        'max_online' => $total,
        'recorded_at' => now(),
      ]);
    }
  }

  public function getMaxOnline()
  {
    $record = OnlineRecord::first();
    return response()->json($record);
  }
}
