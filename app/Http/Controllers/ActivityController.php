<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Topic;
use App\Models\TopicVote;
use App\Models\TopicComment;
use Illuminate\Http\Request;
use App\Models\UserActivity;
use App\Models\AuthAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\TopicCommentVote;

class ActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getActivities(Request $request)
    {
        try {
            // Debug authentication state
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'Missing Authorization header'
                ], 401);
            }

            // Get the token from the Authorization header
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            if (empty($token)) {
                return response()->json([
                    'message' => 'Invalid token format'
                ], 401);
            }

            // Try to get the authenticated user
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                    'debug' => [
                        'has_auth_header' => $request->hasHeader('Authorization'),
                        'auth_header' => $request->header('Authorization'),
                        'auth_check' => Auth::check(),
                    ]
                ], 401);
            }

            // Ensure we have a valid user ID
            $userId = $user->id;
            if (!$userId) {
                return response()->json([
                    'message' => 'Invalid user ID',
                    'debug' => [
                        'user' => $user->toArray()
                    ]
                ], 500);
            }

            // Get liked and disliked posts (votes)
            $votes = TopicVote::where('user_id', $userId)
                ->whereIn('vote_value', [1, -1])
                ->whereHas('topic')
                ->with([
                    'topic' => function ($query) {
                        $query->whereNotNull('id');
                    },
                    'topic.user.profile',
                    'topic.cdnUserContent'
                ])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($vote) {
                    if (!$vote->topic || !$vote->topic->user) {
                        return null;
                    }
                    return [
                        'type' => $vote->vote_value === 1 ? 'like' : 'dislike',
                        'updated_at' => $vote->updated_at->diffForHumans(),
                        'created_timestamp' => $vote->created_at->timestamp,
                        'updated_timestamp' => $vote->updated_at->timestamp,
                        'topic' => [
                            'id' => $vote->topic->id,
                            'title' => $vote->topic->title,
                            'image_url' => $vote->topic->cdnUserContent ? Storage::url($vote->topic->cdnUserContent->file_path) : null,
                            'author' => [
                                'username' => $vote->topic->user->username,
                                'profile_name' => $vote->topic->user->profile->profile_name ?? null,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get comment votes (likes/dislikes)
            $commentVotes = TopicCommentVote::where('user_id', $userId)
                ->whereIn('vote_value', [1, -1])
                ->whereHas('comment')
                ->whereHas('comment.topic')
                ->with([
                    'comment' => function ($query) {
                        $query->whereNotNull('id');
                    },
                    'comment.topic.user.profile'
                ])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($vote) {
                    if (!$vote->comment || !$vote->comment->topic || !$vote->comment->topic->user) {
                        return null;
                    }
                    return [
                        'type' => $vote->vote_value === 1 ? 'comment_like' : 'comment_dislike',
                        'updated_at' => $vote->updated_at->diffForHumans(),
                        'created_timestamp' => $vote->created_at->timestamp,
                        'updated_timestamp' => $vote->updated_at->timestamp,
                        'comment' => [
                            'id' => $vote->comment->id,
                            'content' => $vote->comment->comment,
                        ],
                        'topic' => [
                            'id' => $vote->comment->topic->id,
                            'title' => $vote->comment->topic->title,
                            'image_url' => $vote->comment->topic->cdnUserContent ? Storage::url($vote->comment->topic->cdnUserContent->file_path) : null,
                            'author' => [
                                'username' => $vote->comment->topic->user->username,
                                'profile_name' => $vote->comment->topic->user->profile->profile_name ?? null,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get comments
            $comments = TopicComment::where('user_id', $userId)
                ->whereHas('topic')
                ->whereHas('topic.user')
                ->with([
                    'topic' => function ($query) {
                        $query->whereNotNull('id');
                    },
                    'topic.user.profile',
                    'topic.cdnUserContent'
                ])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($comment) {
                    if (!$comment->topic || !$comment->topic->user) {
                        return null;
                    }
                    return [
                        'type' => 'comment',
                        'updated_at' => $comment->updated_at->diffForHumans(),
                        'created_timestamp' => $comment->created_at->timestamp,
                        'updated_timestamp' => $comment->updated_at->timestamp,
                        'content' => $comment->comment,
                        'topic' => [
                            'id' => $comment->topic->id,
                            'title' => $comment->topic->title,
                            'image_url' => $comment->topic->cdnUserContent ? Storage::url($comment->topic->cdnUserContent->file_path) : null,
                            'author' => [
                                'username' => $comment->topic->user->username,
                                'profile_name' => $comment->topic->user->profile->profile_name ?? null,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get created posts
            $createdPosts = Topic::where('user_id', $userId)
                ->whereNotNull('id')
                ->whereHas('user')
                ->with(['user.profile', 'cdnUserContent'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($topic) {
                    if (!$topic->user) {
                        return null;
                    }
                    return [
                        'type' => 'post',
                        'updated_at' => $topic->updated_at->diffForHumans(),
                        'created_timestamp' => $topic->created_at->timestamp,
                        'updated_timestamp' => $topic->updated_at->timestamp,
                        'topic' => [
                            'id' => $topic->id,
                            'title' => $topic->title,
                            'image_url' => $topic->cdnUserContent ? Storage::url($topic->cdnUserContent->file_path) : null,
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Merge all activities and sort by updated_timestamp
            $allActivities = $votes->concat($commentVotes)->concat($comments)->concat($createdPosts)
                ->sortByDesc('updated_timestamp')
                ->values();

            return response()->json($allActivities);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching activities.',
                'error' => $e->getMessage(),
                'debug' => [
                    'has_auth_header' => $request->hasHeader('Authorization'),
                    'auth_header' => $request->header('Authorization'),
                    'auth_check' => Auth::check(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    public function getLikedPosts(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userId = Auth::id();

        $likedPosts = TopicVote::where('user_id', $userId)
            ->where('vote_value', 1)
            ->whereHas('topic')
            ->whereHas('topic.user')
            ->with(['topic.user.profile', 'topic.cdnUserContent'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($vote) {
                if (!$vote->topic || !$vote->topic->user) {
                    return null;
                }
                return [
                    'updated_at' => $vote->updated_at->diffForHumans(),
                    'topic' => [
                        'id' => $vote->topic->id,
                        'title' => $vote->topic->title,
                        'image_url' => $vote->topic->cdnUserContent ? Storage::url($vote->topic->cdnUserContent->file_path) : null,
                        'author' => [
                            'username' => $vote->topic->user->username,
                            'profile_name' => $vote->topic->user->profile->profile_name ?? null,
                        ]
                    ]
                ];
            })
            ->filter()
            ->values();

        return response()->json($likedPosts);
    }

    public function getCommentedPosts(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userId = Auth::id();

        $comments = TopicComment::where('user_id', $userId)
            ->whereHas('topic')
            ->whereHas('topic.user')
            ->with(['topic.user.profile', 'topic.cdnUserContent'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                if (!$comment->topic || !$comment->topic->user) {
                    return null;
                }
                return [
                    'created_at' => $comment->created_at->diffForHumans(),
                    'content' => $comment->comment,
                    'topic' => [
                        'id' => $comment->topic->id,
                        'title' => $comment->topic->title,
                        'image_url' => $comment->topic->cdnUserContent ? Storage::url($comment->topic->cdnUserContent->file_path) : null,
                        'author' => [
                            'username' => $comment->topic->user->username,
                            'profile_name' => $comment->topic->user->profile->profile_name ?? null,
                        ]
                    ]
                ];
            })
            ->filter()
            ->values();

        return response()->json($comments);
    }

    public function getCreatedPosts(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userId = Auth::id();

        $posts = Topic::where('user_id', $userId)
            ->whereNotNull('id')
            ->whereHas('user')
            ->with(['user.profile', 'cdnUserContent'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($topic) {
                if (!$topic->user) {
                    return null;
                }
                return [
                    'created_at' => $topic->created_at->diffForHumans(),
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'image_url' => $topic->cdnUserContent ? Storage::url($topic->cdnUserContent->file_path) : null,
                    ]
                ];
            })
            ->filter()
            ->values();

        return response()->json($posts);
    }

    public function getOnlineStatus($username)
    {
        $user = AuthAccount::where('username', $username)->firstOrFail();

        // Consider user online if they've been active in the last 5 minutes
        $isOnline = $user->last_activity &&
            Carbon::parse($user->last_activity)->gt(Carbon::now()->subMinutes(5));

        return response()->json([
            'online' => $isOnline,
            'last_seen' => $user->last_activity ? Carbon::parse($user->last_activity)->diffForHumans() : null
        ]);
    }

    public function updateLastActivity()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();
        $user->last_activity = Carbon::now();
        $user->save();

        return response()->json(['message' => 'Last activity updated']);
    }
}
