<?php

namespace App\Http\Controllers;

use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PointsController extends Controller
{
  /**
   * Refresh all user points (background job endpoint)
   *
   * @return JsonResponse
   */
  public function refreshAll(): JsonResponse
  {
    try {
      $results = PointsService::refreshAllPoints();

      return response()->json([
        'status' => 'success',
        'message' => 'Points refreshed successfully',
        'data' => $results
      ]);
    } catch (\Exception $e) {
      Log::error('Failed to refresh all points: ' . $e->getMessage());

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to refresh points: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Refresh points for a specific user
   *
   * @param int $userId
   * @return JsonResponse
   */
  public function refreshUser($userId): JsonResponse
  {
    try {
      $success = PointsService::updateUserPoints($userId);

      if ($success) {
        return response()->json([
          'status' => 'success',
          'message' => 'User points refreshed successfully'
        ]);
      } else {
        return response()->json([
          'status' => 'error',
          'message' => 'Failed to refresh user points'
        ], 500);
      }
    } catch (\Exception $e) {
      Log::error("Failed to refresh points for user {$userId}: " . $e->getMessage());

      return response()->json([
        'status' => 'error',
        'message' => 'Failed to refresh user points: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get top users using cached points
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
          'total_points' => $user->getCachedPoints()
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
