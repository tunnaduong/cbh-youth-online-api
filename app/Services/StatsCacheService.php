<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\Topic;
use App\Models\TopicComment;
use Illuminate\Support\Facades\DB;

class StatsCacheService
{
  /**
   * Get forum statistics (no caching)
   */
  public static function getForumStats()
  {
    return [
      'total_users' => AuthAccount::count(),
      'total_topics' => Topic::count(),
      'total_comments' => TopicComment::count(),
      'max_online' => self::getMaxOnlineUsers(),
    ];
  }

  /**
   * Get online users count (real-time, no cache)
   */
  public static function getOnlineUsersCount()
  {
    return DB::table('cyo_online_users')
      ->where('last_activity', '>=', now()->subMinutes(15))
      ->count();
  }

  /**
   * Get max online users (no caching)
   */
  public static function getMaxOnlineUsers()
  {
    $record = DB::table('cyo_online_record')->first();
    return $record ? $record->max_online : 0;
  }

  /**
   * Get latest user (no caching)
   */
  public static function getLatestUser()
  {
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
  }
}
