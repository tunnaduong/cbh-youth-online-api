<?php

namespace App\Http\Controllers;

use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
      return response()->json(['message' => 'User unblocked successfully']);
    }

    return response()->json(['message' => 'Block not found'], 404);
  }

  /**
   * Get list of blocked users.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $blockedUsers = UserBlock::where('user_id', Auth::id())
      ->with('blockedUser')
      ->get()
      ->pluck('blockedUser');

    return response()->json($blockedUsers);
  }
}
