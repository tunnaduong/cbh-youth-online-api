<?php

namespace App\Http\Controllers;

use App\Models\Follower;
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
use App\Models\StoryViewer;
use App\Models\StoryReaction;
use App\Models\Story;
use App\Models\Follow;

/**
 * Handles user activity-related actions.
 */
class ActivityController extends Controller
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
     * Get all activities for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                            'image_urls' => $vote->topic->getImageUrls()->map(function ($content) {
                                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                            })->all(),
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
                            'image_urls' => $vote->comment->topic->getImageUrls()->map(function ($content) {
                                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                            })->all(),
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
                            'image_urls' => $comment->topic->getImageUrls()->map(function ($content) {
                                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                            })->all(),
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
                            'image_urls' => $topic->getImageUrls()->map(function ($content) {
                                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                            })->all(),
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get saved posts
            $savedPosts = Topic::whereHas('savedTopics', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereHas('user')
            ->with(['user.profile', 'cdnUserContent', 'savedTopics' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('cyo_user_saved_topics.created_at', 'desc')
            ->join('cyo_user_saved_topics', function ($join) use ($userId) {
                $join->on('cyo_topics.id', '=', 'cyo_user_saved_topics.topic_id')
                    ->where('cyo_user_saved_topics.user_id', '=', $userId);
            })
            ->select('cyo_topics.*', 'cyo_user_saved_topics.created_at as saved_at', 'cyo_user_saved_topics.updated_at as saved_updated_at')
            ->get()
            ->map(function ($topic) {
                if (!$topic->user) {
                    return null;
                }
                return [
                    'type' => 'saved',
                    'updated_at' => Carbon::parse($topic->saved_updated_at)->diffForHumans(),
                    'created_timestamp' => Carbon::parse($topic->saved_at)->timestamp,
                    'updated_timestamp' => Carbon::parse($topic->saved_updated_at)->timestamp,
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'image_urls' => $topic->getImageUrls()->map(function ($content) {
                            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                        })->all(),
                        'author' => [
                            'username' => $topic->user->username,
                            'profile_name' => $topic->user->profile->profile_name ?? null,
                        ]
                    ]
                ];
            })
            ->filter()
            ->values();

            // Get story views
            $storyViews = StoryViewer::where('user_id', $userId)
                ->whereHas('story')
                ->whereHas('story.user')
                ->with(['story.user.profile'])
                ->orderBy('viewed_at', 'desc')
                ->get()
                ->map(function ($view) {
                    if (!$view->story || !$view->story->user) {
                        return null;
                    }
                    return [
                        'type' => 'story_view',
                        'updated_at' => $view->viewed_at->diffForHumans(),
                        'created_timestamp' => $view->viewed_at->timestamp,
                        'updated_timestamp' => $view->viewed_at->timestamp,
                        'story' => [
                            'id' => $view->story->id,
                            'media_url' => $view->story->media_url,
                            'media_type' => $view->story->media_type,
                            'author' => [
                                'id' => $view->story->user->id,
                                'username' => $view->story->user->username,
                                'profile_name' => $view->story->user->profile->profile_name ?? $view->story->user->username,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get story reactions
            $storyReactions = StoryReaction::where('user_id', $userId)
                ->whereHas('story')
                ->whereHas('story.user')
                ->with(['story.user.profile'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reaction) {
                    if (!$reaction->story || !$reaction->story->user) {
                        return null;
                    }
                    return [
                        'type' => 'story_reaction',
                        'reaction_type' => $reaction->reaction_type,
                        'updated_at' => $reaction->updated_at->diffForHumans(),
                        'created_timestamp' => $reaction->created_at->timestamp,
                        'updated_timestamp' => $reaction->updated_at->timestamp,
                        'story' => [
                            'id' => $reaction->story->id,
                            'media_url' => $reaction->story->media_url,
                            'media_type' => $reaction->story->media_type,
                            'author' => [
                                'id' => $reaction->story->user->id,
                                'username' => $reaction->story->user->username,
                                'profile_name' => $reaction->story->user->profile->profile_name ?? $reaction->story->user->username,
                            ]
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Get user's stories
            $userStories = Story::where('user_id', $userId)
                ->with(['user.profile'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($story) {
                    return [
                        'type' => 'story_create',
                        'updated_at' => $story->updated_at->diffForHumans(),
                        'created_timestamp' => $story->created_at->timestamp,
                        'updated_timestamp' => $story->updated_at->timestamp,
                        'story' => [
                            'id' => $story->id,
                            'media_url' => $story->media_url,
                            'media_type' => $story->media_type,
                            'expires_at' => $story->expires_at
                        ]
                    ];
                });

            // Get following relationships
            $following = Follower::where('follower_id', $userId)
                ->with(['followed.profile'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($follow) {
                    if (!$follow->followed) {
                        return null;
                    }
                    return [
                        'type' => 'follow',
                        'updated_at' => $follow->created_at->diffForHumans(),
                        'created_timestamp' => $follow->created_at->timestamp,
                        'updated_timestamp' => $follow->created_at->timestamp,
                        'following' => [
                            'id' => $follow->followed->id,
                            'username' => $follow->followed->username,
                            'profile_name' => $follow->followed->profile->profile_name ?? $follow->followed->username,
                        ]
                    ];
                })
                ->filter()
                ->values();

            // Merge all activities and sort by updated_timestamp
            $allActivities = $votes->concat($commentVotes)
                ->concat($comments)
                ->concat($createdPosts)
                ->concat($savedPosts)
                ->concat($storyViews)
                ->concat($storyReactions)
                ->concat($userStories)
                ->concat($following)
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

    /**
     * Get the posts liked by the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                        'image_urls' => $vote->topic->getImageUrls()->map(function ($content) {
                            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                        })->all(),
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

    /**
     * Get the posts commented on by the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                        'image_urls' => $comment->topic->getImageUrls()->map(function ($content) {
                            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                        })->all(),
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

    /**
     * Get the posts created by the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                        'image_urls' => $topic->getImageUrls()->map(function ($content) {
                            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                        })->all(),
                    ]
                ];
            })
            ->filter()
            ->values();

        return response()->json($posts);
    }

    /**
     * Get the online status of a user.
     *
     * @param  string  $username
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Update the last activity timestamp for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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
