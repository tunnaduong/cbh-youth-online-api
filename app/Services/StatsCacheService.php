<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\Topic;
use App\Models\TopicComment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsCacheService
{
  /**
   * Get cached statistics for the forum
   */
  public static function getForumStats()
  {
    return Cache::remember('forum_stats', 300, function () {
      return [
        'total_users' => AuthAccount::count(),
        'total_topics' => Topic::count(),
        'total_comments' => TopicComment::count(),
        'max_online' => self::getMaxOnlineUsers(),
      ];
    });
  }

  /**
   * Get online users count
   */
  public static function getOnlineUsersCount()
  {
    return Cache::remember('online_users_count', 60, function () {
      return DB::table('cyo_online_users')
        ->where('last_activity', '>=', now()->subMinutes(5))
        ->count();
    });
  }

  /**
   * Get max online users
   */
  public static function getMaxOnlineUsers()
  {
    return Cache::remember('max_online_users', 300, function () {
      $record = DB::table('cyo_online_record')->first();
      return $record ? $record->max_online : 0;
    });
  }

  /**
   * Get latest user
   */
  public static function getLatestUser()
  {
    return Cache::remember('latest_user', 300, function () {
      $user = AuthAccount::with('profile')
        ->orderBy('created_at', 'desc')
        ->first();

      if (!$user)
        return null;

      return [
        'id' => $user->id,
        'username' => $user->username,
        'profile' => [
          'profile_name' => $user->profile->profile_name ?? null,
        ],
        'created_at' => $user->created_at,
      ];
    });
  }

  /**
   * Clear all cached stats
   */
  public static function clearStats()
  {
    Cache::forget('forum_stats');
    Cache::forget('online_users_count');
    Cache::forget('max_online_users');
    Cache::forget('latest_user');
  }

  /**
   * Clear user-specific caches
   */
  public static function clearUserCaches($userId)
  {
    Cache::forget("user_saved_topics_{$userId}");
  }
}
