<?php

namespace App\Http\Controllers;

use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PointsController extends Controller
{
  /**
   * Get top users using points
   *
   * @param int $limit
   * @return JsonResponse
   */
  public function getTopUsers($limit = 8): JsonResponse
  {
    try {
      $topUsers = PointsService::getTopUsers($limit);

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
