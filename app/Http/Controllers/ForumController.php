<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\AuthAccount;
use Illuminate\Support\Str;
use App\Models\TopicComment;
use Illuminate\Http\Request;
use App\Models\ForumCategory;
use App\Models\ForumSubforum;
use App\Models\ForumMainCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $mainCategories = ForumCategory::with([
            'subforums' => function ($query) {
                $query->withCount(['topics', 'comments']);
            },
            'subforums.latestPublicTopic.user.profile'
        ])->orderBy('arrange', 'asc')->get();

        // Handle sorting based on query parameter
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'latest':
                $latestPosts = $this->getLatestPosts();
                break;
            case 'most_viewed':
                $latestPosts = $this->getMostViewedPosts();
                break;
            case 'most_engaged':
                $latestPosts = $this->getMostEngagedPosts();
                break;
            default:
                $latestPosts = $this->getLatestPosts();
                break;
        }

        $userCount = AuthAccount::count();
        $postCount = Topic::count();
        $commentCount = TopicComment::count();

        $record = DB::table('cyo_online_record')->first();

        $onlineUsers = DB::table('cyo_online_users')
            ->where('last_activity', '>=', now()->subMinutes(5))
            ->get();

        $stats = (object) [
            'total' => $onlineUsers->count(),
            'registered' => $onlineUsers->where('user_id', '!=', null)->where('is_hidden', false)->count(),
            'hidden' => $onlineUsers->where('user_id', '!=', null)->where('is_hidden', true)->count(),
            'guests' => $onlineUsers->where('user_id', null)->count()
        ];

        $visitors = $stats;

        $latestUser = AuthAccount::orderBy('created_at', 'desc')->with('profile')->first();

        return Inertia::render('Home', [
            'latestPosts' => $latestPosts,
            'mainCategories' => $mainCategories,
            'currentSort' => $sort,
            'stats' => [
                'userCount' => $userCount,
                'postCount' => $postCount,
                'commentCount' => $commentCount,
                'record' => $record,
                'visitors' => $visitors,
                'latestUser' => $latestUser,
            ],
        ]);
    }

    public function feed()
    {
        $query = $this->buildFeedQuery();

        // Paginate posts
        $paginatedPosts = $query->paginate(10);

        $posts = collect($paginatedPosts->items())->map(function ($post) {
            return $this->formatPostData($post);
        });

        return Inertia::render('Feed/Index', [
            'posts' => $posts,
            'pagination' => [
                'current_page' => $paginatedPosts->currentPage(),
                'last_page' => $paginatedPosts->lastPage(),
                'per_page' => $paginatedPosts->perPage(),
                'total' => $paginatedPosts->total(),
                'has_more_pages' => $paginatedPosts->hasMorePages(),
            ]
        ]);
    }

    public function feedApi()
    {
        $query = $this->buildFeedQuery();

        // Paginate posts
        $paginatedPosts = $query->paginate(10);

        $posts = collect($paginatedPosts->items())->map(function ($post) {
            return $this->formatPostData($post);
        });

        return response()->json([
            'posts' => $posts,
            'pagination' => [
                'current_page' => $paginatedPosts->currentPage(),
                'last_page' => $paginatedPosts->lastPage(),
                'per_page' => $paginatedPosts->perPage(),
                'total' => $paginatedPosts->total(),
                'has_more_pages' => $paginatedPosts->hasMorePages(),
            ]
        ]);
    }

    private function buildFeedQuery()
    {
        $query = Topic::with(['user.profile', 'comments', 'votes'])
            ->withCount(['comments as reply_count', 'views'])
            ->orderBy('created_at', 'desc')
            ->where('hidden', false);

        // Filter by privacy based on authentication and following status
        if (auth()->check()) {
            $userId = auth()->id();

            // Get list of user IDs that the current user is following
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        // Followers posts
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            // For non-authenticated users, only show public posts
            $query->where('privacy', 'public');
        }

        return $query;
    }

    private function formatPostData($post)
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content_html,
            'image_urls' => $post->getImageUrls()->map(function ($content) {
                return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
            })->all(),
            'author' => $post->anonymous ? [
                'id' => null,
                'username' => 'Ẩn danh',
                'email' => null,
                'profile_name' => 'Người dùng ẩn danh',
                'verified' => false,
            ] : [
                'id' => $post->user->id,
                'username' => $post->user->username,
                'email' => $post->user->email,
                'profile_name' => $post->user->profile->profile_name ?? null,
                'verified' => $post->user->profile->verified == 1 ?? false ? true : false,
            ],
            'anonymous' => $post->anonymous,
            'created_at' => Carbon::parse($post->created_at)->diffForHumans(),
            'reply_count' => $this->roundToNearestFive($post->reply_count) . '+',
            'view_count' => $post->views_count,
            'votes' => $post->votes->map(function ($vote) {
                return [
                    'username' => $vote->user->username,
                    'vote_value' => $vote->vote_value,
                    'created_at' => $vote->created_at->toISOString(),
                    'updated_at' => $vote->updated_at->toISOString(),
                ];
            }),
        ];
    }

    public static function updateMaxOnline()
    {
        $onlineUsers = DB::table('cyo_online_users')
            ->where('last_activity', '>=', now()->subMinutes(5))
            ->get();

        $total = $onlineUsers->count();

        $record = DB::table('cyo_online_record')->first();

        if (!$record || $total > $record->max_online) {
            if ($record) {
                DB::table('cyo_online_record')
                    ->where('id', 1)
                    ->update([
                        'max_online' => $total,
                        'recorded_at' => now()
                    ]);
            } else {
                DB::table('cyo_online_record')->insert([
                    'id' => 1,
                    'max_online' => $total,
                    'recorded_at' => now()
                ]);
            }
        }
    }

    public function category(ForumCategory $category)
    {
        $category->load([
            'subforums' => function ($query) {
                $query->withCount(['topics', 'comments'])
                    ->with([
                        'latestPublicTopic' => function ($q) {
                            $q->with(['user.profile']);
                        }
                    ]);
            }
        ]);

        return Inertia::render('Forum/Category', [
            'category' => $category
        ]);
    }

    public function subforum(ForumCategory $category, ForumSubforum $subforum)
    {
        $query = $subforum->topics()
            ->with(['user.profile', 'comments'])
            ->withCount(['comments as reply_count', 'views'])
            ->orderBy('pinned', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            // For non-authenticated users, only show public posts
            $query->where('privacy', 'public');
        }

        $topics = $query->get();

        return Inertia::render('Forum/Subforum', [
            'category' => $category,
            'subforum' => $subforum,
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'content' => Str::limit($topic->description, 100),
                    'pinned' => $topic->pinned,
                    'anonymous' => $topic->anonymous,
                    'created_at' => $topic->created_at->diffForHumans(),
                    'updated_at' => $topic->updated_at->diffForHumans(),
                    'reply_count' => $this->roundToNearestFive($topic->reply_count),
                    'view_count' => $topic->views_count,
                    'author' => $topic->anonymous ? [
                        'id' => null,
                        'username' => 'Ẩn danh',
                        'profile_name' => 'Người dùng ẩn danh',
                        'avatar' => null,
                        'verified' => false
                    ] : [
                        'id' => $topic->user->id,
                        'username' => $topic->user->username,
                        'profile_name' => $topic->user->profile->profile_name ?? null,
                        'avatar' => "https://api.chuyenbienhoa.com/v1.0/users/" . $topic->user->username . "/avatar",
                        'verified' => $topic->user->profile->verified == 1 ? true : false
                    ],
                    'latest_reply' => $topic->comments->sortByDesc('created_at')->first() ? [
                        'created_at' => $topic->comments->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                        'updated_at' => $topic->comments->sortByDesc('created_at')->first()->updated_at->diffForHumans(),
                        'user' => [
                            'username' => $topic->comments->sortByDesc('created_at')->first()->user->username,
                            'profile_name' => $topic->comments->sortByDesc('created_at')->first()->user->profile->profile_name ?? null
                        ]
                    ] : null
                ];
            })
        ]);
    }

    public function createTopic(ForumSubforum $subforum)
    {
        return Inertia::render('Posts/Create', [
            'subforum' => $subforum
        ]);
    }

    public function storeTopic(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'subforum_id' => 'required|exists:cyo_forum_subforums,id'
        ]);

        $topic = Topic::create([
            ...$validated,
            'user_id' => auth()->id()
        ]);

        return redirect()->route('forum.topic.show', $topic)
            ->with('success', 'Chủ đề đã được tạo thành công.');
    }

    public function showTopic(Topic $topic)
    {
        $topic->load(['user', 'subforum']);
        $replies = $topic->replies()
            ->with('user')
            ->orderBy('created_at')
            ->paginate(20);

        return Inertia::render('Posts/Show', [
            'topic' => $topic,
            'replies' => $replies
        ]);
    }

    public function editTopic(Topic $topic)
    {
        $this->authorize('update', $topic);

        return Inertia::render('Posts/Edit', [
            'topic' => $topic
        ]);
    }

    public function updateTopic(Request $request, Topic $topic)
    {
        $this->authorize('update', $topic);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        $topic->update($validated);

        return redirect()->route('forum.topic.show', $topic)
            ->with('success', 'Chủ đề đã được cập nhật thành công.');
    }

    public function destroyTopic(Topic $topic)
    {
        $this->authorize('delete', $topic);

        $subforum = $topic->subforum;
        $topic->delete();

        return redirect()->route('forum.subforum', $subforum)
            ->with('success', 'Chủ đề đã được xóa thành công.');
    }

    // public function storeReply(Request $request, Topic $topic)
    // {
    //     $validated = $request->validate([
    //         'content' => 'required|string'
    //     ]);

    //     Reply::create([
    //         ...$validated,
    //         'topic_id' => $topic->id,
    //         'user_id' => auth()->id()
    //     ]);

    //     return redirect()->route('forum.topic.show', $topic)
    //         ->with('success', 'Trả lời đã được gửi thành công.');
    // }

    // public function updateReply(Request $request, Reply $reply)
    // {
    //     $this->authorize('update', $reply);

    //     $validated = $request->validate([
    //         'content' => 'required|string'
    //     ]);

    //     $reply->update($validated);

    //     return redirect()->route('forum.topic.show', $reply->topic)
    //         ->with('success', 'Trả lời đã được cập nhật thành công.');
    // }

    // public function destroyReply(Reply $reply)
    // {
    //     $this->authorize('delete', $reply);

    //     $topic = $reply->topic;
    //     $reply->delete();

    //     return redirect()->route('forum.topic.show', $topic)
    //         ->with('success', 'Trả lời đã được xóa thành công.');
    // }

    public function getSubforumsByRole(Request $request)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $role = $user->role;

            $subforums = ForumSubforum::with([
                'mainCategory',
                'topics' => function ($query) {
                    $query->visibleToCurrentUser()
                        ->latest('created_at')
                        ->with(['user.profile']);
                }
            ])
                ->where('active', true)
                ->get();

            if ($role === 'admin') {
                $subforums = $subforums->filter(function ($subforum) {
                    return in_array($subforum->role_restriction, ['user', 'admin']);
                });
            } elseif ($role === 'teacher') {
                $subforums = $subforums->filter(function ($subforum) {
                    return in_array($subforum->role_restriction, ['user', 'teacher']);
                });
            } else {
                $subforums = $subforums->filter(function ($subforum) {
                    return $subforum->role_restriction === 'user';
                });
            }
        } else {
            $subforums = ForumSubforum::with([
                'mainCategory',
                'topics' => function ($query) {
                    // For non-authenticated users, only show public posts
                    $query->where('privacy', 'public')
                        ->latest('created_at')
                        ->with(['user.profile']);
                }
            ])
                ->where('active', true)
                ->get();
        }

        $transformedSubforums = $subforums->sortBy(function ($subforum) {
            return $subforum->mainCategory->arrange;
        })->values()->map(function ($subforum) {
            return [
                'label' => $subforum->name,
                'value' => $subforum->id,
            ];
        });

        return response()->json($transformedSubforums);
    }

    public function getCategories()
    {
        $categories = ForumMainCategory::with([
            'subforums' => function ($query) {
                // Giữ lại phần đếm số topic và comment
                $query->withCount(['topics', 'comments']);
            },
            // Sử dụng relationship 'latestPublicTopic' để chỉ hiển thị bài viết public
            'subforums.latestPublicTopic.user.profile'
        ])
            ->orderBy('arrange', 'asc')
            ->get();

        $categories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'subforums' => $category->subforums->map(function ($subforum) {
                    $latestPost = $subforum->latestPublicTopic;
                    return [
                        'id' => $subforum->id,
                        'main_category_id' => $subforum->main_category_id,
                        'name' => $subforum->name,
                        'description' => $subforum->description,
                        'active' => $subforum->active,
                        'pinned' => $subforum->pinned,
                        'created_at' => $subforum->created_at,
                        'updated_at' => $subforum->updated_at,
                        'post_count' => $subforum->topics_count,
                        'comment_count' => $subforum->comment_count ?? 0,
                        'latest_post' => $latestPost ? [
                            'id' => $latestPost->id,
                            'title' => $latestPost->title,
                            'created_at' => $latestPost->created_at->diffForHumans(),
                            'user' => [
                                'name' => $latestPost->user->profile->profile_name ?? null,
                                'username' => $latestPost->user->username,
                                'verified' => $latestPost->user->profile->verified ?? null,
                            ],
                        ] : null,
                    ];
                })
            ];
        });

        return response()->json($categories);
    }

    public function getSubforums(ForumMainCategory $mainCategory)
    {
        $subforums = $mainCategory->subforums()->where('active', true)->withCount('topics')->with([
            'topics' => function ($query) {
                // Apply privacy filtering first, then find the latest visible topic
                if (auth()->check()) {
                    $userId = auth()->id();
                    $followingIds = \App\Models\Follower::where('follower_id', $userId)
                        ->pluck('followed_id')
                        ->toArray();

                    $query->where(function ($q) use ($userId, $followingIds) {
                        $q->where('privacy', 'public')
                            ->orWhere('user_id', $userId) // User's own posts (including private ones)
                            ->orWhere(function ($subQ) use ($followingIds) {
                                // Followers posts
                                $subQ->where('privacy', 'followers')
                                    ->whereIn('user_id', $followingIds);
                            });
                    });
                } else {
                    // For non-authenticated users, only show public posts
                    $query->where('privacy', 'public');
                }

                // Now get the latest topic from the filtered results
                $query->latest('created_at');
            }
        ])->get();

        return $subforums->map(function ($subforum) use ($mainCategory) {
            $latestPost = $subforum->topics->first();

            return [
                'id' => $subforum->id,
                'main_category_id' => $subforum->main_category_id,
                'main_category_name' => $mainCategory->name,
                'name' => $subforum->name,
                'description' => $subforum->description,
                'active' => $subforum->active,
                'pinned' => $subforum->pinned,
                'created_at' => $subforum->created_at,
                'updated_at' => $subforum->updated_at,
                'post_count' => $subforum->topics_count,
                'comment_count' => $subforum->comment_count ?? 0,
                'latest_post' => $latestPost ? [
                    'id' => $latestPost->id,
                    'title' => $latestPost->title,
                    'created_at' => $latestPost->created_at->diffForHumans(),
                    'user' => [
                        'name' => $latestPost->user->profile->profile_name ?? null,
                        'username' => $latestPost->user->username,
                        'verified' => $latestPost->user->profile->verified ?? null,
                    ],
                ] : null,
            ];
        });
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $category = ForumMainCategory::create($request->all());
        return response()->json($category);
    }

    public function storeSubforum(Request $request, ForumMainCategory $mainCategory)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $subforum = $mainCategory->subforums()->create($request->all());
        return response()->json($subforum);
    }

    public function getPinnedTopics()
    {
        return Topic::where('pinned', true)->get();
    }

    public function pinTopic(Topic $topic, Request $request)
    {
        $topic->pinned = $request->input('pinned', false);
        $topic->save();

        return response()->json($topic);
    }

    public function show($username, $id)
    {
        // Extract the numeric ID from the slug format (e.g., "123-my-post-title" -> "123")
        $postId = intval(explode('-', $id)[0]);

        // Find the post first
        $post = Topic::with(['author.profile', 'comments.user.profile', 'user', 'votes.user', 'cdnUserContent'])
            ->withCount(['comments as reply_count', 'views'])
            ->findOrFail($postId);

        // Check privacy settings
        if ($post->privacy === 'private') {
            // Only the author can see private posts (privacy = private)
            if (!auth()->check() || $post->user_id !== auth()->id()) {
                abort(403, 'Bạn không có quyền xem bài viết này');
            }
        } elseif ($post->privacy === 'followers') {
            // Only followers can see followers-only posts
            if (!auth()->check()) {
                abort(403, 'Bạn cần đăng nhập để xem bài viết này');
            }

            if ($post->user_id !== auth()->id()) {
                $isFollowing = \App\Models\Follower::where('follower_id', auth()->id())
                    ->where('followed_id', $post->user_id)
                    ->exists();

                if (!$isFollowing) {
                    abort(403, 'Bạn cần theo dõi tác giả để xem bài viết này');
                }
            }
        }

        // Handle anonymous posts
        if ($post->anonymous) {
            // For anonymous posts, the username in URL should be "anonymous"
            if ($username !== 'anonymous') {
                return redirect()->route('posts.show', [
                    'username' => 'anonymous',
                    'id' => $id
                ], 301);
            }
        } else {
            // For regular posts, verify the username matches the author
            $user = AuthAccount::where('username', $username)->firstOrFail();
            if ($post->user_id !== $user->id) {
                abort(404);
            }
        }

        // Get the correct slug
        $titleSlug = str()->slug($post->title, '-');
        if (empty($titleSlug)) {
            $titleSlug = 'untitled';
        }
        $correctSlug = $postId . '-' . $titleSlug;

        // If the slug is wrong, redirect to the correct URL
        if ($id !== $correctSlug) {
            $correctUsername = $post->anonymous ? 'anonymous' : $username;
            return redirect()->route('posts.show', [
                'username' => $correctUsername,
                'id' => $correctSlug
            ], 301); // 301 Permanent redirect for SEO
        }

        // Load comments with their respective votes and voter usernames
        $comments = $post->comments()
            ->whereNull('replying_to')
            ->with([
                'user.profile',
                'votes.user',
                'replies' => function ($q) {
                    $q->with([
                        'user.profile',
                        'votes.user',
                    ]); // Load 5 replies per request
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();


        $formattedComments = $comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->comment,
                'author' => [
                    'id' => $comment->user->id,
                    'username' => $comment->user->username,
                    'email' => $comment->user->email,
                    'profile_name' => $comment->user->profile->profile_name ?? null,
                    'verified' => $comment->user->profile->verified == 1 ?? false ? true : false,
                ],
                'created_at' => $comment->created_at->diffForHumans(),
                'votes' => $comment->votes->map(fn($vote) => [
                    'user_id' => $vote->user_id,
                    'username' => $vote->user->username,
                    'vote_value' => $vote->vote_value,
                ]),
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->comment,
                        'author' => [
                            'id' => $reply->user->id,
                            'username' => $reply->user->username,
                            'email' => $reply->user->email,
                            'profile_name' => $reply->user->profile->profile_name ?? null,
                            'verified' => $reply->user->profile->verified == 1 ?? false ? true : false,
                        ],
                        'created_at' => $reply->created_at->diffForHumans(),
                        'votes' => $reply->votes->map(fn($vote) => [
                            'user_id' => $vote->user_id,
                            'username' => $vote->user->username,
                            'vote_value' => $vote->vote_value,
                        ]),
                        'replies' => $reply->replies->map(function ($subReply) {
                            return [
                                'id' => $subReply->id,
                                'content' => $subReply->comment,
                                'author' => [
                                    'id' => $subReply->user->id,
                                    'username' => $subReply->user->username,
                                    'email' => $subReply->user->email,
                                    'profile_name' => $subReply->user->profile->profile_name ?? null,
                                    'verified' => $subReply->user->profile->verified == 1 ?? false ? true : false,
                                ],
                                'created_at' => $subReply->created_at->diffForHumans(),
                                'votes' => $subReply->votes->map(fn($vote) => [
                                    'user_id' => $vote->user_id,
                                    'username' => $vote->user->username,
                                    'vote_value' => $vote->vote_value,
                                ]),
                            ];
                        }),
                    ];
                }),
            ];
        });

        return Inertia::render('Posts/Show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content_html,
                'image_urls' => $post->getImageUrls()->map(function ($content) {
                    return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
                })->all(),
                'votes' => $post->votes->map(function ($vote) {
                    return [
                        'username' => $vote->user->username, // Assuming votes relation includes the user
                        'vote_value' => $vote->vote_value,
                        'created_at' => $vote->created_at->toISOString(),
                        'updated_at' => $vote->updated_at->toISOString(),
                    ];
                }),
                'reply_count' => $this->roundToNearestFive($post->reply_count) . "+",
                'view_count' => $post->views_count,
                'created_at' => $post->created_at->diffForHumans(),
                'author' => $post->anonymous ? [
                    'username' => 'Ẩn danh',
                    'profile_name' => 'Người dùng ẩn danh',
                    'verified' => false,
                ] : [
                    'username' => $post->author->username,
                    'profile_name' => $post->author->profile->profile_name ?? null,
                    'verified' => $post->user->profile->verified == 1 ?? false ? true : false,
                ],
                'anonymous' => $post->anonymous,
                'comments' => $formattedComments,
            ]
        ]);
    }

    public function getSubforumPosts(ForumSubforum $subforum)
    {
        $query = $subforum->topics()
            ->with(['user.profile', 'comments'])
            ->withCount(['comments as reply_count', 'views'])
            ->orderBy('pinned', 'desc')
            ->orderBy('updated_at', 'desc');

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            // For non-authenticated users, only show public posts
            $query->where('privacy', 'public');
        }

        $topics = $query->get();

        return response()->json([
            'subforum' => [
                'id' => $subforum->id,
                'name' => $subforum->name,
                'description' => $subforum->description,
                'background' => "https://chuyenbienhoa.com/assets/images/" . $subforum->background_image
            ],
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'content' => Str::limit($topic->description, 100),
                    'pinned' => $topic->pinned,
                    'anonymous' => $topic->anonymous,
                    'created_at' => $topic->created_at->diffForHumans(),
                    'updated_at' => $topic->updated_at->diffForHumans(),
                    'reply_count' => $this->roundToNearestFive($topic->reply_count),
                    'view_count' => $topic->views_count,
                    'author' => $topic->anonymous ? [
                        'id' => null,
                        'username' => 'Ẩn danh',
                        'profile_name' => 'Người dùng ẩn danh',
                        'avatar' => null,
                        'verified' => false
                    ] : [
                        'id' => $topic->user->id,
                        'username' => $topic->user->username,
                        'profile_name' => $topic->user->profile->profile_name ?? null,
                        'avatar' => "https://api.chuyenbienhoa.com/v1.0/users/" . $topic->user->username . "/avatar",
                        'verified' => $topic->user->profile->verified == 1 ? true : false
                    ],
                    'latest_reply' => $topic->comments->sortByDesc('created_at')->first() ? [
                        'created_at' => $topic->comments->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                        'updated_at' => $topic->comments->sortByDesc('created_at')->first()->updated_at->diffForHumans(),
                        'user' => [
                            'username' => $topic->comments->sortByDesc('created_at')->first()->user->username,
                            'profile_name' => $topic->comments->sortByDesc('created_at')->first()->user->profile->profile_name ?? null
                        ]
                    ] : null
                ];
            })
        ]);
    }

    private function roundToNearestFive($count)
    {
        if ($count <= 5) {
            // If count is less than or equal to 5, format it with leading zero
            return str_pad($count, 2, '0', STR_PAD_LEFT);
        } else {
            // Round down to the nearest multiple of 5 and pad to 2 digits
            return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
        }
    }

    // Lấy tất cả bài viết mới nhất trong tất cả các danh mục
    private function getLatestPosts()
    {
        $query = Topic::with('user.profile')
            ->orderBy('created_at', 'desc')
            ->take(10);

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        // Followers posts
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            $query->where('privacy', 'public');
        }

        return $query->get();
    }

    // Lấy các bài viết có lượt xem nhiều nhất
    private function getMostViewedPosts()
    {
        $query = Topic::with('user.profile')
            ->withCount('views')
            ->orderBy('views_count', 'desc')
            ->take(10);

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        // Followers posts
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            $query->where('privacy', 'public');
        }

        return $query->get();
    }

    // Lấy các bài viết có lượt xem và lượt tương tác (bình luận, like) nhiều nhất
    private function getMostEngagedPosts()
    {
        $query = Topic::with('user.profile')
            ->withCount(['comments', 'votes'])
            ->orderByRaw('(comments_count + votes_count) DESC')
            ->take(10);

        // Apply privacy filtering
        if (auth()->check()) {
            $userId = auth()->id();
            $followingIds = \App\Models\Follower::where('follower_id', $userId)
                ->pluck('followed_id')
                ->toArray();

            $query->where(function ($q) use ($userId, $followingIds) {
                $q->where('privacy', 'public')
                    ->orWhere('user_id', $userId) // User's own posts (including private ones)
                    ->orWhere(function ($subQ) use ($followingIds) {
                        // Followers posts
                        $subQ->where('privacy', 'followers')
                            ->whereIn('user_id', $followingIds);
                    });
            });
        } else {
            $query->where('privacy', 'public');
        }

        return $query->get();
    }
}
