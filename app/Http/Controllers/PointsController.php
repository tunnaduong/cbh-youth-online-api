<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PointsController extends Controller
{
  /**
   * Get top users using points
   *
   * @param int $limit
   * @return JsonResponse
   */
  public function getTopUsers(Request $request): JsonResponse
  {
    try {
      $limit = $request->query('limit', 8);

      $topUsers = AuthAccount::with(['profile'])
        ->where('role', '!=', 'admin')
        ->orderByDesc('points')
        ->limit($limit)
        ->get();

      $formattedUsers = $topUsers->map(function ($user) {
        return [
          'uid' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? $user->username,
          'profile_picture' => $user->profile->profile_picture ?? null,
          'oauth_profile_picture' => $user->profile->oauth_profile_picture ?? null,
          'total_points' => $user->getPoints()
        ];
      });

      return response()->json($formattedUsers);
    } catch (\Exception $e) {
      Log::error('Failed to get top users: ' . $e->getMessage());

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to fetch top users: ' . $e->getMessage()
      ], 500);
    }
  }
}
