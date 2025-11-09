<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryViewer;
use App\Models\StoryReaction;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\NotificationService;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Inertia\Inertia;

/**
 * Handles the logic for creating, viewing, and interacting with user stories.
 */
class StoryController extends Controller
{
  /**
   * Get all active stories, grouped by user.
   * For authenticated users, it shows public and followers' stories.
   * For non-authenticated users, it shows only public stories.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    // For logged-out users, only show public stories
    // For authenticated users, show public and followers stories
    $privacyLevels = $request->user() ? ['public', 'followers'] : ['public'];

    $stories = Story::with(['user', 'viewers', 'reactions'])
      ->active()
      ->whereIn('privacy', $privacyLevels)
      ->orderByDesc('pinned') // Order by pinned first
      ->orderBy('created_at', 'asc') // Order by oldest first so latest stories appear at end
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
              'text_content' => $story->content,
              'type' => $story->media_type,
              'background_color' => $story->background_color,
              'font_style' => $story->font_style,
              'text_position' => $story->text_position,
              'created_at' => $story->created_at ? $story->created_at->toISOString() : null,
              'created_at_human' => $story->created_at->diffForHumans(),
              'duration' => $story->duration ?? 10,
              'expires_at' => $story->expires_at,
              'user_id' => $story->user_id,
              'pinned' => $story->pinned, // Add pinned status
              'viewers' => $story->viewers,
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

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'data' => $stories
      ]);
    }

    // For Inertia requests, return JSON with proper structure
    return response()->json($stories);
  }

  /**
   * Store a newly created story in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function store(Request $request)
  {
    // Base validation rules
    $rules = [
      'content' => 'nullable|string',
      'media_type' => 'required|in:image,video,audio,text',
      'privacy' => 'required|in:public,followers,private',
      'duration' => 'nullable|integer|min:1|max:30',
      'expires_at' => 'nullable|date'
    ];

    // Add media-specific validation based on media_type
    if ($request->media_type === 'text') {
      $rules['background_color'] = 'nullable|string';
      $rules['font_style'] = 'nullable|string';
      $rules['text_position'] = 'nullable|json';
    } else {
      $rules['media_file'] = 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,mp3,wav|max:100240';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => $validator->errors()
        ], 422);
      }

      return back()->withErrors($validator)->withInput();
    }

    $data = $request->all();
    $data['user_id'] = Auth::id();

    // Set expires_at only if not provided
    if (!isset($data['expires_at'])) {
      $data['expires_at'] = Carbon::now()->addHours(24);
    }

    // Handle data conversion for database storage
    if (isset($data['background_color']) && is_array($data['background_color'])) {
      $data['background_color'] = json_encode($data['background_color']);
    }

    if (isset($data['text_position']) && is_array($data['text_position'])) {
      $data['text_position'] = json_encode($data['text_position']);
    }

    // Handle media upload if present
    if ($request->hasFile('media_file')) {
      $file = $request->file('media_file');
      $path = $file->store('stories', 'public');
      $data['media_url'] = Storage::url($path);
    }

    $story = Story::create($data);

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'data' => $story->load(['user', 'viewers', 'reactions'])
      ], 201);
    }

    return back()->with('success', 'Story created successfully!');
  }

  /**
   * Display the specified story.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function show(Request $request, Story $story)
  {
    if ($story->hasExpired()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Story has expired'
        ], 404);
      }
      return back()->with('error', 'Story has expired');
    }

    $story->load(['user', 'viewers', 'reactions']);

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'data' => $story
      ]);
    }

    // For web requests, redirect to home with story parameter
    return redirect()->route('home', ['story' => $story->id]);
  }

  /**
   * Remove the specified story from storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function destroy(Request $request, Story $story)
  {
    if ($story->user_id !== Auth::id()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Unauthorized'
        ], 403);
      }
      return back()->with('error', 'Unauthorized');
    }

    // Delete associated media file if exists
    if ($story->media_url) {
      $filePath = str_replace('/storage/', '', $story->media_url);
      Storage::disk('public')->delete($filePath);
    }

    $story->delete();

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'message' => 'Story deleted successfully'
      ]);
    }

    return back();
  }

  /**
   * Mark a story as viewed by the authenticated user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function markAsViewed(Request $request, Story $story)
  {
    if ($story->hasExpired()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Story has expired'
        ], 404);
      }
      return back()->with('error', 'Story has expired');
    }

    StoryViewer::firstOrCreate([
      'story_id' => $story->id,
      'user_id' => Auth::id(),
    ], [
      'viewed_at' => now()
    ]);

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'message' => 'Story marked as viewed'
      ]);
    }

    return back();
  }

  /**
   * Add or update a reaction to a story.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function react(Request $request, Story $story)
  {
    $validator = Validator::make($request->all(), [
      'reaction_type' => 'required|in:like,love,haha,wow,sad,angry'
    ]);

    if ($validator->fails()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => $validator->errors()
        ], 422);
      }
      return back()->withErrors($validator);
    }

    if ($story->hasExpired()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Story has expired'
        ], 404);
      }
      return back()->with('error', 'Story has expired');
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

    // Create notification for story reaction
    // Only notify if user is reacting to someone else's story
    if ($story->user_id !== Auth::id()) {
      NotificationService::createStoryReactionNotification($story, Auth::id(), $request->reaction_type);
    }

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'data' => $reaction
      ]);
    }

    return back();
  }

  /**
   * Remove a reaction from a story.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function removeReaction(Request $request, Story $story)
  {
    $reaction = StoryReaction::where([
      'story_id' => $story->id,
      'user_id' => Auth::id(),
    ])->first();

    if ($reaction) {
      $reaction->delete();
    }

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'message' => 'Reaction removed successfully'
      ]);
    }

    return back();
  }

  /**
   * Reply to a story by sending a message to the story owner.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Story  $story
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function reply(Request $request, Story $story)
  {
    $validator = Validator::make($request->all(), [
      'content' => 'required|string|max:5000',
    ]);

    if ($validator->fails()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => $validator->errors()
        ], 422);
      }
      return back()->withErrors($validator);
    }

    if ($story->hasExpired()) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Story has expired'
        ], 404);
      }
      return back()->with('error', 'Story has expired');
    }

    $user = Auth::user();
    $storyOwnerId = $story->user_id;

    // Don't allow replying to your own story
    if ($user->id === $storyOwnerId) {
      if ($request->expectsJson()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Cannot reply to your own story'
        ], 400);
      }
      return back()->with('error', 'Cannot reply to your own story');
    }

    // Load story owner information
    $story->load('user.profile');
    $storyOwner = $story->user;
    $storyOwnerName = $storyOwner->profile->profile_name ?? $storyOwner->username;

    // Find or create conversation between current user and story owner
    $conversation = Conversation::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->whereHas('participants', function ($query) use ($storyOwnerId) {
      $query->where('user_id', $storyOwnerId);
    })->where('type', 'private')->first();

    if (!$conversation) {
      // Create new conversation
      $conversation = Conversation::create(['type' => 'private']);
      $conversation->participants()->attach([$user->id, $storyOwnerId]);
    }

    // Create message with story reference in content
    $messageContent = $request->content;

    // Create the message with metadata for story reply
    $message = Message::create([
      'conversation_id' => $conversation->id,
      'user_id' => $user->id,
      'content' => $messageContent,
      'type' => 'text',
      'metadata' => [
        'story_reply' => true,
        'story_id' => $story->id,
        'story_owner_id' => $storyOwnerId,
        'story_owner_name' => $storyOwnerName,
        'story_owner_username' => $storyOwner->username,
      ],
    ]);

    // Update conversation's updated_at timestamp
    $conversation->touch();

    // Load relationships for the response
    $message->load('user.profile');

    // Prepare message data for broadcasting (similar to ChatController)
    $senderData = [
      'id' => $message->user->id,
      'username' => $message->user->username ?? 'Ẩn danh',
      'profile_name' => ($message->user->profile->profile_name ?? null) ?? $message->user->username ?? 'Ẩn danh',
      'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
    ];

    $messageData = [
      'id' => $message->id,
      'content' => $message->content,
      'type' => $message->type,
      'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
      'is_edited' => $message->is_edited,
      'is_myself' => false, // For the recipient, this is not their own message
      'sender' => $senderData,
      'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
      'created_at_human' => $message->created_at->diffForHumans(),
      'read_at' => $message->read_at?->toISOString(),
      'metadata' => $message->metadata, // Include metadata for story reply
    ];

    // Broadcast the message to other participants
    broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

    // Create notification for story reply (after message is created and broadcasted)
    NotificationService::createStoryReplyNotification($story, $message, Auth::id());

    if ($request->expectsJson()) {
      return response()->json([
        'status' => 'success',
        'data' => [
          'message' => [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type,
            'conversation_id' => $conversation->id,
            'metadata' => $message->metadata,
            'sender' => [
              'id' => $message->user->id,
              'username' => $message->user->username,
              'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
              'avatar_url' => config('app.url') . "/v1.0/users/{$message->user->username}/avatar",
            ],
            'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
          ],
          'conversation_id' => $conversation->id,
        ]
      ], 201);
    }

    return back();
  }
}
