<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\UserBlock;
use App\Support\UserBlocks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserBlockController extends Controller
{
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->middleware('auth:sanctum');
  }

  /**
   * Block a user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $request->validate([
      'blocked_user_id' => 'required|exists:cyo_auth_accounts,id|different:user_id',
    ]);

    $blockedUserId = $request->blocked_user_id;

    if ($blockedUserId == Auth::id()) {
      return response()->json(['message' => 'You cannot block yourself'], 400);
    }

    $existingBlock = UserBlock::where('user_id', Auth::id())
      ->where('blocked_user_id', $blockedUserId)
      ->first();

    if ($existingBlock) {
      return response()->json(['message' => 'User already blocked'], 400);
    }

    $block = UserBlock::create([
      'user_id' => Auth::id(),
      'blocked_user_id' => $blockedUserId,
    ]);

    // Blocking severs the follow relationship in both directions, so the
    // blocked user stops receiving followers-only posts and neither side
    // keeps showing up in the other's follower/following lists.
    Follower::where(function ($q) use ($blockedUserId) {
      $q->where('follower_id', Auth::id())->where('followed_id', $blockedUserId);
    })->orWhere(function ($q) use ($blockedUserId) {
      $q->where('follower_id', $blockedUserId)->where('followed_id', Auth::id());
    })->delete();

    UserBlocks::forget((int) Auth::id());
    $this->forgetFeedCaches((int) Auth::id(), (int) $blockedUserId);

    return response()->json([
      'message' => 'User blocked successfully',
      'data' => $block
    ]);
  }

  /**
   * Unblock a user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request)
  {
    $request->validate([
      'blocked_user_id' => 'required|exists:cyo_auth_accounts,id',
    ]);

    $deleted = UserBlock::where('user_id', Auth::id())
      ->where('blocked_user_id', $request->blocked_user_id)
      ->delete();

    if ($deleted) {
      UserBlocks::forget((int) Auth::id());
      $this->forgetFeedCaches((int) Auth::id(), (int) $request->blocked_user_id);
      return response()->json(['message' => 'User unblocked successfully']);
    }

    return response()->json(['message' => 'Block not found'], 404);
  }

  /**
   * The personalized feed caches a ranked topic-id list per user for up to
   * 30 minutes (see TopicsController::feed). Drop both sides' caches so a
   * new block/unblock is reflected on the next feed load instead of only
   * after the TTL expires.
   */
  private function forgetFeedCaches(int ...$userIds): void
  {
    $feedVersion = Cache::get('feed_version', 1);
    foreach ($userIds as $userId) {
      Cache::forget("feed_scores_v3_user_{$userId}_v{$feedVersion}");
    }
  }

  /**
   * Get list of blocked users.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $blockedUsers = UserBlock::where('user_id', Auth::id())
      ->with('blockedUser.profile')
      ->get()
      ->pluck('blockedUser');

    return response()->json($blockedUsers);
  }
}
