<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\TopicComment;
use App\Models\UserPointDeduction;
use Illuminate\Support\Facades\Log;

class PointsService
{
  /**
   * Calculate points for a specific user
   *
   * @param int $userId
   * @return int
   */
  public static function calculatePoints($userId)
  {
    $user = AuthAccount::find($userId);
    if (!$user) {
      return 0;
    }

    // Calculate total points based on posts, likes, and comments
    $postsCount = $user->posts()->count();
    $totalLikes = $user->posts()->withCount([
      'votes' => function ($query) {
        $query->where('vote_value', 1);
      }
    ])->get()->sum('votes_count');
    $commentsCount = TopicComment::where('user_id', $userId)->count();

    $basePoints = ($postsCount * 10) + ($totalLikes * 5) + ($commentsCount * 2);

    // Boost specific users (for testing/admin purposes)
    $boostedUsers = [
      // 'tunnaduong' => 5000,    // Add 5000 points to tunna
      // 'admin' => 10000,   // Add 10000 points to admin
    ];

    if (isset($boostedUsers[$user->username])) {
      $basePoints += $boostedUsers[$user->username];
    }

    // Subtract point deductions
    $totalDeductions = UserPointDeduction::getTotalActiveDeductions($userId);
    $finalPoints = $basePoints - $totalDeductions;

    // Ensure points don't go below 0
    return max(0, $finalPoints);
  }

  /**
   * Update cached points for a specific user
   *
   * @param int $userId
   * @return bool
   */
  public static function updateUserPoints($userId)
  {
    try {
      $points = self::calculatePoints($userId);

      AuthAccount::where('id', $userId)
        ->update(['cached_points' => $points]);

      return true;
    } catch (\Exception $e) {
      Log::error("Failed to update points for user {$userId}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update cached points for all users (background job)
   *
   * @return array
   */
  public static function refreshAllPoints()
  {
    $results = [
      'success' => 0,
      'failed' => 0,
      'total' => 0
    ];

    try {
      $users = AuthAccount::where('role', '!=', 'admin')->get();
      $results['total'] = $users->count();

      foreach ($users as $user) {
        if (self::updateUserPoints($user->id)) {
          $results['success']++;
        } else {
          $results['failed']++;
        }
      }

      Log::info("Points refresh completed: {$results['success']} success, {$results['failed']} failed");
    } catch (\Exception $e) {
      Log::error("Failed to refresh all points: " . $e->getMessage());
    }

    return $results;
  }

  /**
   * Get top users using cached points
   *
   * @param int $limit
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public static function getTopUsers($limit = 8)
  {
    return AuthAccount::with(['profile'])
      ->where('role', '!=', 'admin')
      ->orderByDesc('cached_points')
      ->limit($limit)
      ->get();
  }

  /**
   * Update points when user creates a post
   *
   * @param int $userId
   * @return void
   */
  public static function onPostCreated($userId)
  {
    self::updateUserPoints($userId);
  }

  /**
   * Update points when user receives a vote
   *
   * @param int $userId
   * @return void
   */
  public static function onVoteReceived($userId)
  {
    self::updateUserPoints($userId);
  }

  /**
   * Update points when user creates a comment
   *
   * @param int $userId
   * @return void
   */
  public static function onCommentCreated($userId)
  {
    self::updateUserPoints($userId);
  }

  /**
   * Update points when user gets point deduction
   *
   * @param int $userId
   * @return void
   */
  public static function onPointDeduction($userId)
  {
    self::updateUserPoints($userId);
  }
}
