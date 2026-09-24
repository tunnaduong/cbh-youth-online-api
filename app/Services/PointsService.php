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
        // lockForUpdate() - without it, two concurrent point mutations for
        // the same user (e.g. a double-tapped withdrawal, or two award
        // events landing at once) both read the same starting balance and
        // each save their own read-modify-write, silently losing one of the
        // two changes (lost update). This matters most for
        // WalletController::requestWithdrawal(), where the lost update can
        // let a second concurrent withdrawal request through against a
        // balance that should already have been reduced by the first.
        $user = AuthAccount::where('id', $userId)->lockForUpdate()->first();
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
        // See addPoints() above for why this needs lockForUpdate().
        $user = AuthAccount::where('id', $userId)->lockForUpdate()->first();
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
   * Add points for time spent playing a game (1 XP per full minute played).
   *
   * @param int $userId
   * @param int $xp
   * @param int $gameSessionId
   * @return void
   */
  public static function onGamePlayed($userId, $xp, $gameSessionId)
  {
    if ($xp <= 0) {
      return;
    }
    self::addPoints($userId, $xp, 'game', 'Chơi game', $gameSessionId);
  }

  /**
   * Add points for a completed quiz attempt. Points come pre-weighted by
   * difficulty per correct answer (see QuizController::DIFFICULTY_POINTS).
   *
   * @param int $userId
   * @param int $points
   * @param int $quizSetPlayId
   * @return void
   */
  public static function onQuizCompleted($userId, $points, $quizSetPlayId)
  {
    if ($points <= 0) {
      return;
    }
    self::addPoints($userId, $points, 'quiz', 'Hoàn thành đố vui', $quizSetPlayId);
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

  /**
   * Points awarded per streak day (day 1–7+).
   * Day 7+ always gets the max reward (20 points).
   */
  public static function checkinPointsForStreak(int $streakDay): int
  {
    $table = [1 => 5, 2 => 7, 3 => 9, 4 => 11, 5 => 13, 6 => 15, 7 => 20];
    return $table[min($streakDay, 7)];
  }

  /**
   * Process daily check-in for a user.
   *
   * Returns an array with:
   *   - already_checked_in (bool)
   *   - streak_day (int)
   *   - points_awarded (int)
   *   - total_points (int)
   *
   * @param int $userId
   * @return array
   */
  public static function onDailyCheckin(int $userId): array
  {
    $today = now()->toDateString();

    $existing = \App\Models\DailyCheckin::where('user_id', $userId)
      ->where('checkin_date', $today)
      ->first();

    if ($existing) {
      $user = AuthAccount::find($userId);
      return [
        'already_checked_in' => true,
        'streak_day' => $existing->streak_day,
        'points_awarded' => $existing->points_awarded,
        'total_points' => $user?->points ?? 0,
      ];
    }

    // Determine streak: check if user checked in yesterday
    $yesterday = now()->subDay()->toDateString();
    $lastCheckin = \App\Models\DailyCheckin::where('user_id', $userId)
      ->orderByDesc('checkin_date')
      ->first();

    if ($lastCheckin && $lastCheckin->checkin_date->toDateString() === $yesterday) {
      $streakDay = $lastCheckin->streak_day + 1;
    } else {
      $streakDay = 1;
    }

    $points = self::checkinPointsForStreak($streakDay);

    DB::transaction(function () use ($userId, $today, $streakDay, $points) {
      \App\Models\DailyCheckin::create([
        'user_id' => $userId,
        'checkin_date' => $today,
        'points_awarded' => $points,
        'streak_day' => $streakDay,
      ]);

      self::addPoints($userId, $points, 'checkin', "Điểm danh ngày {$streakDay}", null);
    });

    $user = AuthAccount::find($userId);
    return [
      'already_checked_in' => false,
      'streak_day' => $streakDay,
      'points_awarded' => $points,
      'total_points' => $user?->points ?? 0,
    ];
  }

  /** Tier privilege that unlocks gifting points to other members. */
  public const GIFT_PRIVILEGE = 'gift_points_to_others';
  public const GIFT_MIN_AMOUNT = 1;
  public const GIFT_MAX_AMOUNT = 1000;

  /**
   * Whether this user is allowed to gift points at all (tier privilege,
   * admins always can).
   */
  public static function canGiftPoints(AuthAccount $user): bool
  {
    return ($user->role ?? null) === 'admin' || $user->hasPrivilege(self::GIFT_PRIVILEGE);
  }

  /**
   * Move points from one member to another as a gift.
   *
   * Both balances are updated inside one DB transaction with row locks
   * (taken in id order so two members gifting each other at once can't
   * deadlock), and each side gets its own 'gift' transaction record so the
   * transfer shows up in both wallets' history.
   *
   * @param int $senderId
   * @param int $recipientId
   * @param int $amount Points to transfer (positive)
   * @param string $senderDescription History line for the sender
   * @param string $recipientDescription History line for the recipient
   * @param int|null $relatedId Topic id the gift was made from, if any
   * @return array{sender_points:int,recipient_points:int}
   * @throws \InvalidArgumentException when the transfer is not allowed
   */
  public static function giftPoints(
    int $senderId,
    int $recipientId,
    int $amount,
    string $senderDescription,
    string $recipientDescription,
    ?int $relatedId = null
  ): array {
    if ($senderId === $recipientId) {
      throw new \InvalidArgumentException('Bạn không thể tự tặng điểm cho chính mình.');
    }
    if ($amount < self::GIFT_MIN_AMOUNT || $amount > self::GIFT_MAX_AMOUNT) {
      throw new \InvalidArgumentException(
        'Số điểm tặng phải từ ' . self::GIFT_MIN_AMOUNT . ' đến ' . number_format(self::GIFT_MAX_AMOUNT) . ' điểm.'
      );
    }

    return DB::transaction(function () use ($senderId, $recipientId, $amount, $senderDescription, $recipientDescription, $relatedId) {
      // Lock in a stable order regardless of who is sending - see addPoints()
      // for why the lock is needed in the first place.
      $ids = [$senderId, $recipientId];
      sort($ids);
      $locked = AuthAccount::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
      $sender = $locked->get($senderId);
      $recipient = $locked->get($recipientId);

      if (!$sender || !$recipient) {
        throw new \InvalidArgumentException('Không tìm thấy người nhận.');
      }
      if (!self::canGiftPoints($sender)) {
        throw new \InvalidArgumentException('Bạn cần đạt hạng Thành viên tích cực (150 điểm) để tặng điểm cho người khác.');
      }

      $senderPoints = (int) ($sender->points ?? 0);
      if ($senderPoints < $amount) {
        throw new \InvalidArgumentException('Số dư không đủ. Bạn đang có ' . number_format($senderPoints) . ' điểm.');
      }

      $sender->points = $senderPoints - $amount;
      $sender->save();

      $recipient->points = (int) ($recipient->points ?? 0) + $amount;
      $recipient->save();

      PointsTransaction::create([
        'user_id' => $senderId,
        'type' => 'gift',
        'amount' => -$amount,
        'status' => 'completed',
        'description' => $senderDescription,
        'related_id' => $relatedId,
      ]);
      PointsTransaction::create([
        'user_id' => $recipientId,
        'type' => 'gift',
        'amount' => $amount,
        'status' => 'completed',
        'description' => $recipientDescription,
        'related_id' => $relatedId,
      ]);

      Log::info("Points gifted: user {$senderId} -> user {$recipientId} ({$amount})");

      return [
        'sender_points' => (int) $sender->points,
        'recipient_points' => (int) $recipient->points,
      ];
    });
  }
}
