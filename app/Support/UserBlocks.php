<?php

namespace App\Support;

use App\Models\UserBlock;

/**
 * Request-scoped helper for "who is blocked relative to whom".
 *
 * Blocking is treated as symmetric for visibility purposes: once either side
 * blocks the other, neither should see the other's profile, posts, comments,
 * chat messages, stories, notifications, search results, follower rows, etc.
 * Every read endpoint should go through these helpers (or the model scopes
 * that wrap them) instead of re-querying cyo_user_blocks by hand.
 */
class UserBlocks
{
  /** @var array<int, int[]> ids the given user has blocked */
  private static array $blockedCache = [];

  /** @var array<int, int[]> ids that have blocked the given user */
  private static array $blockedByCache = [];

  /**
   * Users that $userId has blocked.
   *
   * @return int[]
   */
  public static function blockedIds(int $userId): array
  {
    if (!array_key_exists($userId, self::$blockedCache)) {
      self::$blockedCache[$userId] = UserBlock::where('user_id', $userId)
        ->pluck('blocked_user_id')
        ->map(fn($id) => (int) $id)
        ->all();
    }

    return self::$blockedCache[$userId];
  }

  /**
   * Users that have blocked $userId.
   *
   * @return int[]
   */
  public static function blockedByIds(int $userId): array
  {
    if (!array_key_exists($userId, self::$blockedByCache)) {
      self::$blockedByCache[$userId] = UserBlock::where('blocked_user_id', $userId)
        ->pluck('user_id')
        ->map(fn($id) => (int) $id)
        ->all();
    }

    return self::$blockedByCache[$userId];
  }

  /**
   * Union of both directions - the set of users $userId must not see and
   * must not be seen by.
   *
   * @return int[]
   */
  public static function eitherWayIds(int $userId): array
  {
    return array_values(array_unique(array_merge(
      self::blockedIds($userId),
      self::blockedByIds($userId)
    )));
  }

  /**
   * Either-way block set for the currently authenticated user, or an empty
   * array for guests. Safe to drop straight into whereNotIn('user_id', ...).
   *
   * @return int[]
   */
  public static function eitherWayIdsForViewer(): array
  {
    $viewerId = auth()->id();

    return $viewerId ? self::eitherWayIds((int) $viewerId) : [];
  }

  public static function isBlockedEitherWay(int $userIdA, int $userIdB): bool
  {
    if ($userIdA === $userIdB) {
      return false;
    }

    return in_array($userIdB, self::eitherWayIds($userIdA), true);
  }

  /**
   * Whether the current viewer and $otherUserId are blocked either way.
   * Guests are never blocked.
   */
  public static function viewerIsBlockedWith(int $otherUserId): bool
  {
    $viewerId = auth()->id();

    return $viewerId ? self::isBlockedEitherWay((int) $viewerId, $otherUserId) : false;
  }

  /**
   * Drop rows (votes, reactions, viewers...) whose user_id belongs to
   * someone blocked either way relative to the current viewer. Rows with a
   * missing user (NULL / deleted) are dropped too since they can't be shown.
   *
   * @param  \Illuminate\Support\Collection  $rows
   * @return \Illuminate\Support\Collection
   */
  public static function withoutBlockedUsers($rows)
  {
    $hidden = self::eitherWayIdsForViewer();
    if (empty($hidden)) {
      return $rows;
    }

    return $rows
      ->filter(fn($row) => $row->user_id !== null && !in_array((int) $row->user_id, $hidden, true))
      ->values();
  }

  /**
   * Drop cached sets after a block/unblock so the same request sees the
   * change (e.g. the block endpoint then returning a refreshed list).
   */
  public static function forget(int $userId): void
  {
    unset(self::$blockedCache[$userId], self::$blockedByCache[$userId]);
    // The other side's "blocked by" set changed too; cheapest is to clear all.
    self::$blockedByCache = [];
    self::$blockedCache = [];
  }
}
