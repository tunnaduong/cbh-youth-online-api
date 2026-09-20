<?php

namespace App\Http\Controllers;

use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyCheckinController extends Controller
{
  /**
   * Perform daily check-in for the authenticated user.
   */
  public function checkin(Request $request): JsonResponse
  {
    $user = $request->user();
    $result = PointsService::onDailyCheckin($user->id);

    return response()->json([
      'already_checked_in' => $result['already_checked_in'],
      'streak_day' => $result['streak_day'],
      'points_awarded' => $result['points_awarded'],
      'total_points' => $result['total_points'],
    ]);
  }

  /**
   * Get check-in status for today.
   */
  public function status(Request $request): JsonResponse
  {
    $user = $request->user();
    $today = now()->toDateString();

    $checkin = \App\Models\DailyCheckin::where('user_id', $user->id)
      ->where('checkin_date', $today)
      ->first();

    $lastCheckin = \App\Models\DailyCheckin::where('user_id', $user->id)
      ->orderByDesc('checkin_date')
      ->first();

    $streakDay = $lastCheckin ? $lastCheckin->streak_day : 0;

    // If last checkin was not today or yesterday, streak resets
    if ($lastCheckin) {
      $yesterday = now()->subDay()->toDateString();
      $last = $lastCheckin->checkin_date->toDateString();
      if ($last !== $today && $last !== $yesterday) {
        $streakDay = 0;
      }
    }

    return response()->json([
      'checked_in_today' => (bool) $checkin,
      'streak_day' => $streakDay,
      'next_reward' => PointsService::checkinPointsForStreak($streakDay + 1),
    ]);
  }
}
