<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryViewer;
use App\Models\StoryReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StoryController extends Controller
{
    /**
     * Get all active stories for the authenticated user and their connections
     */
    public function index()
    {
        $stories = Story::with(['user', 'viewers', 'reactions'])
        ->active()
        ->whereIn('privacy', ['public', 'followers'])
        ->orderBy('created_at', 'asc')
        ->get()
        ->groupBy('user_id')
        ->map(function ($userStories, $userId) {
            $firstStory = $userStories->first();
            $user = $firstStory->user;

            return [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->profile->profile_name ?? $user->username,
                'stories' => $userStories->map(function ($story) {
                    return [
                        'id' => (string) $story->id,
                        'media_url' => $story->media_url,
                        'type' => $story->media_type,
                        'created_at' => $story->created_at->toISOString(),
                        'duration' => $story->duration ?? 10,
                        'expires_at' => $story->expires_at,
                        'reactions' => $story->reactions->map(function ($reaction) {
                            return [
                                'type' => $reaction->reaction_type,
                                'user' => $reaction->user->username
                            ];
                        })
                    ];
                })->values()->toArray()
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $stories
        ]);
    }

    /**
     * Store a new story
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string',
            'media_type' => 'nullable|in:image,video,audio,text',
            'media_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,mp3,wav|max:10240', // 10MB max
            'background_color' => 'nullable|string',
            'font_style' => 'nullable|string',
            'text_position' => 'nullable|json',
            'privacy' => 'required|in:public,followers',
            'duration' => 'nullable|integer',
            'expires_at' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['user_id'] = Auth::id();

        // Set expires_at only if not provided
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = Carbon::now()->addHours(24);
        }

        // Handle media upload if present
        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            $path = $file->store('stories', 'public');
            $data['media_url'] = Storage::url($path);
        }

        $story = Story::create($data);

        return response()->json([
            'status' => 'success',
            'data' => $story->load(['user', 'viewers', 'reactions'])
        ], 201);
    }

    /**
     * Get a specific story
     */
    public function show(Story $story)
    {
        if ($story->hasExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Story has expired'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $story->load(['user', 'viewers', 'reactions'])
        ]);
    }

    /**
     * Delete a story
     */
    public function destroy(Story $story)
    {
        if ($story->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete associated media file if exists
        if ($story->media_url) {
            $path = str_replace('/storage/', '', $story->media_url);
            Storage::disk('public')->delete($path);
        }

        $story->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Story deleted successfully'
        ]);
    }

    /**
     * Mark a story as viewed
     */
    public function markAsViewed(Story $story)
    {
        if ($story->hasExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Story has expired'
            ], 404);
        }

        StoryViewer::firstOrCreate([
            'story_id' => $story->id,
            'user_id' => Auth::id(),
        ], [
            'viewed_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Story marked as viewed'
        ]);
    }

    /**
     * React to a story
     */
    public function react(Request $request, Story $story)
    {
        $validator = Validator::make($request->all(), [
            'reaction_type' => 'required|in:like,love,haha,wow,sad,angry'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        if ($story->hasExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Story has expired'
            ], 404);
        }

        $reaction = StoryReaction::updateOrCreate(
            [
                'story_id' => $story->id,
                'user_id' => Auth::id(),
            ],
            [
                'reaction_type' => $request->reaction_type
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $reaction
        ]);
    }

    /**
     * Remove a reaction from a story
     */
    public function removeReaction(Story $story)
    {
        $reaction = StoryReaction::where([
            'story_id' => $story->id,
            'user_id' => Auth::id(),
        ])->first();

        if ($reaction) {
            $reaction->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reaction removed successfully'
        ]);
    }
}
