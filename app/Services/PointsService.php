<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\PointsTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointsService
{
  /**
   * Add points directly to user's account
   *
   * @param int $userId
   * @param int $amount
   * @param string $type deposit|withdrawal|purchase|earning|post|vote|comment
   * @param string $description
   * @param int|null $relatedId
   * @return bool
   */
  public static function addPoints($userId, $amount, $type, $description, $relatedId = null)
  {
    try {
      DB::transaction(function () use ($userId, $amount, $type, $description, $relatedId) {
        // Update user's points
        $user = AuthAccount::find($userId);
        if (!$user) {
          throw new \Exception('User not found');
        }

        $oldPoints = $user->points ?? 0;
        $newPoints = max(0, $oldPoints + $amount);
        $user->points = $newPoints;
        $user->save();

        Log::info("Points added for user {$userId}: {$oldPoints} -> {$newPoints} (+{$amount})");

        // Create transaction record
        PointsTransaction::create([
          'user_id' => $userId,
          'type' => $type,
          'amount' => $amount,
          'status' => 'completed',
          'description' => $description,
          'related_id' => $relatedId,
        ]);
      });

      return true;
    } catch (\Exception $e) {
      Log::error("Failed to add points for user {$userId}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Deduct points directly from user's account
   *
   * @param int $userId
   * @param int $amount
   * @param string $type deposit|withdrawal|purchase|earning|post|vote|comment
   * @param string $description
   * @param int|null $relatedId
   * @return bool
   */
  public static function deductPoints($userId, $amount, $type, $description, $relatedId = null)
  {
    try {
      DB::transaction(function () use ($userId, $amount, $type, $description, $relatedId) {
        // Update user's points
        $user = AuthAccount::find($userId);
        if (!$user) {
          throw new \Exception('User not found');
        }

        $oldPoints = $user->points ?? 0;
        $newPoints = max(0, $oldPoints - $amount);
        $user->points = $newPoints;
        $user->save();

        Log::info("Points deducted for user {$userId}: {$oldPoints} -> {$newPoints} (-{$amount})");

        // Create transaction record (amount is negative)
        PointsTransaction::create([
          'user_id' => $userId,
          'type' => $type,
          'amount' => -$amount,
          'status' => 'completed',
          'description' => $description,
          'related_id' => $relatedId,
        ]);
      });

      return true;
    } catch (\Exception $e) {
      Log::error("Failed to deduct points for user {$userId}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Convert points to VND
   * 10 points = 1.000 VND
   *
   * @param int $points
   * @return float
   */
  public static function convertPointsToVND($points)
  {
    return ($points / 10) * 1000;
  }

  /**
   * Convert VND to points
   * 1.000 VND = 10 points
   *
   * @param float $vnd
   * @return int
   */
  public static function convertVNDToPoints($vnd)
  {
    return (int) round(($vnd / 1000) * 10);
  }

  /**
   * Check if user can withdraw the specified amount
   * Minimum withdrawal: 500 points = 50.000 VND
   *
   * @param int $userId
   * @param int $amount Points to withdraw
   * @return bool
   */
  public static function canWithdraw($userId, $amount)
  {
    $user = AuthAccount::find($userId);
    if (!$user) {
      return false;
    }

    $currentPoints = $user->points ?? 0;
    $minWithdrawal = 500;  // 500 points = 50.000 VND

    return $amount >= $minWithdrawal && $currentPoints >= $amount;
  }

  /**
   * Process withdrawal request
   * Deducts points including fee (10 points = 1.000 VND fee)
   *
   * @param int $requestId WithdrawalRequest ID
   * @return bool
   */
  public static function processWithdrawal($requestId)
  {
    $withdrawalRequest = \App\Models\WithdrawalRequest::find($requestId);
    if (!$withdrawalRequest || $withdrawalRequest->status !== 'approved') {
      return false;
    }

    $fee = 10;  // 10 points = 1.000 VND
    $totalDeduction = $withdrawalRequest->amount + $fee;

    return self::deductPoints(
      $withdrawalRequest->user_id,
      $totalDeduction,
      'withdrawal',
      "Rút tiền: {$withdrawalRequest->amount} điểm (phí: {$fee} điểm)",
      $requestId
    );
  }

  /**
   * Get top users by points
   *
   * @param int $limit
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public static function getTopUsers($limit = 8)
  {
    return AuthAccount::with(['profile'])
      ->where('role', '!=', 'admin')
      ->orderByDesc('points')
      ->limit($limit)
      ->get();
  }

  /**
   * Add points when user creates a post (+10 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onPostCreated($userId)
  {
    self::addPoints($userId, 10, 'post', 'Đăng bài viết mới', null);
  }

  /**
   * Deduct points when user deletes a post (-10 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onPostDeleted($userId)
  {
    self::deductPoints($userId, 10, 'post', 'Xóa bài viết', null);
  }

  /**
   * Add points when user receives a vote (+5 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onVoteReceived($userId)
  {
    self::addPoints($userId, 5, 'vote', 'Nhận vote từ thành viên', null);
  }

  /**
   * Deduct points when vote is removed (-5 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onVoteRemoved($userId)
  {
    self::deductPoints($userId, 5, 'vote', 'Mất vote từ thành viên', null);
  }

  /**
   * Add points when user creates a comment (+2 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onCommentCreated($userId)
  {
    self::addPoints($userId, 2, 'comment', 'Bình luận trên bài viết', null);
  }

  /**
   * Deduct points when user deletes a comment (-2 points)
   *
   * @param int $userId
   * @return void
   */
  public static function onCommentDeleted($userId)
  {
    self::deductPoints($userId, 2, 'comment', 'Xóa bình luận', null);
  }

  /**
   * Deduct points when admin applies point deduction
   *
   * @param int $userId
   * @param int $amount
   * @return void
   */
  public static function onPointDeduction($userId, $amount)
  {
    self::deductPoints($userId, $amount, 'deduction', 'Admin trừ điểm', null);
  }
}
