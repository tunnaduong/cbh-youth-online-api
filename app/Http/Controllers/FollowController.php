<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\AuthAccount;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    // Follow a user
    public function follow(Request $request, $id)
    {
        $followerId = auth()->id(); // Get the authenticated user's ID
        $followedId = $id; // The ID of the user to follow

        // Check if already following
        if (Follower::where('follower_id', $followerId)->where('followed_id', $followedId)->exists()) {
            return response()->json(['message' => 'Bạn đã theo dõi người dùng này rồi.'], 400);
        }

        // Create the follow relationship
        Follower::create([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return response()->json(['message' => 'Theo dõi thành công.'], 200);
    }

    // Unfollow a user
    public function unfollow(Request $request, $id)
    {
        $followerId = auth()->id(); // Get the authenticated user's ID
        $followedId = $id; // The ID of the user to unfollow

        // Delete the follow relationship
        $deleted = Follower::where('follower_id', $followerId)->where('followed_id', $followedId)->delete();

        if ($deleted) {
            return response()->json(['message' => 'Bỏ theo dõi thành công.'], 200);
        }

        return response()->json(['message' => 'Bạn chưa theo dõi người này.'], 400);
    }
}
