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
use Illuminate\Support\Facades\Auth;
use App\Models\UserSavedTopic;

/**
 * Handles the display and interaction with the main forum, categories, subforums, and topics.
 */
class ForumController extends Controller
{
  /**
   * Display the main forum index page.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $mainCategories = ForumCategory::with([
      'subforums' => function ($query) {
        $query->withCount(['topics', 'comments']);
      }
    ])->orderBy('arrange', 'asc')->get();

    // Get all subforum IDs
    $subforumIds = $mainCategories->pluck('subforums')->flatten()->pluck('id')->toArray();

    // Load latest public topic for all subforums in one query (không filter hidden)
    $latestTopics = Topic::select(['id', 'subforum_id', 'title', 'created_at', 'cyo_topics.user_id', 'anonymous'])
      ->with(['user:id,username', 'user.profile:id,auth_account_id,profile_name,verified'])
      ->whereIn('subforum_id', $subforumIds)
      ->where('cyo_topics.privacy', 'public')
      ->orderBy('subforum_id')
      ->orderBy('created_at', 'desc')
      ->get()
      ->groupBy('subforum_id')
      ->map(function ($topics) {
        return $topics->first();
      });

    // Assign latest topics to subforums
    foreach ($mainCategories as $category) {
      foreach ($category->subforums as $subforum) {
        $subforum->latest_public_topic = $latestTopics->get($subforum->id);
      }
    }

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

    // Get forum stats directly (no caching)
    $userCount = AuthAccount::count();
    $postCount = Topic::count();
    $commentCount = TopicComment::count();

    // Lấy online record với recorded_at
    $onlineRecord = DB::table('cyo_online_record')->first();
    $record = (object) [
      'max_online' => $onlineRecord ? $onlineRecord->max_online : 0,
      'recorded_at' => $onlineRecord ? $onlineRecord->recorded_at : now()
    ];

    // Get online users stats from OnlineUserController
    $onlineUserController = new \App\Http\Controllers\OnlineUserController();
    $visitors = $onlineUserController->getStats()->getData(true);

    // Additional fallback: If no visitors are tracked, assume at least 1 guest
    if ($visitors['total'] === 0) {
      $visitors = [
        'total' => 1,
        'registered' => 0,
        'hidden' => 0,
        'guests' => 1,
      ];
    }

    // Get latest user directly (no caching)
    $user = AuthAccount::with('profile')
      ->orderBy('created_at', 'desc')
      ->first();

    $latestUser = $user ? [
      'id' => $user->id,
      'username' => $user->username,
      'profile' => [
        'profile_name' => $user->profile->profile_name ?? null,
      ],
      'created_at' => $user->created_at,
    ] : null;

    return response()->json([
      'latestPosts' => $this->formatLatestPosts($latestPosts),
      'mainCategories' => $this->formatMainCategories($mainCategories),
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

  /**
   * Display the user's personalized feed.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function feed()
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

  /**
   * Get the user's personalized feed as a JSON response.
   *
   * @return \Illuminate\Http\JsonResponse
   */
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
    $query = Topic::with([
      'user.profile',
      'comments' => function ($query) {
        $query->select('id', 'topic_id', 'user_id', 'comment', 'created_at')
          ->with('user:id,username')
          ->latest()
          ->limit(5); // Chỉ load 5 comment gần nhất
      },
      'votes' => function ($query) {
        $query->select('id', 'topic_id', 'user_id', 'vote_value', 'created_at')
          ->with('user:id,username')
          ->latest()
          ->limit(10); // Chỉ load 10 vote gần nhất
      }
    ])
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
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            // Followers posts
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      // For non-authenticated users, only show public posts
      $query->where('cyo_topics.privacy', 'public');
    }

    return $query;
  }

  private function formatPostData($post)
  {
    $isSaved = false;
    if (Auth::check()) {
      // Get saved topics directly (no caching)
      $savedTopics = UserSavedTopic::where('user_id', Auth::id())
        ->pluck('topic_id')
        ->toArray();
      $isSaved = in_array($post->id, $savedTopics);
    }

    return [
      'id' => $post->id,
      'title' => $post->title,
      'content' => $post->content_html,
      'image_urls' => $post->getImageUrls()->map(function ($content) {
        return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
      })->all(),
      'document_urls' => $post->getDocuments()->map(function ($content) {
        return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
      })->all(),
      'document_sizes' => $post->getDocuments()->map(function ($content) {
        return $content->file_size;
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
      'is_saved' => $isSaved,
      'votes' => $post->votes->map(function ($vote) {
        return [
          'username' => $vote->user->username,
          'vote_value' => $vote->vote_value,
          'created_at' => $vote->created_at ? $vote->created_at->toISOString() : null,
          'updated_at' => $vote->updated_at ? $vote->updated_at->toISOString() : null,
        ];
      }),
    ];
  }

  /**
   * Update the maximum number of online users.
   * @deprecated Use OnlineUserController::updateMaxOnline() instead
   */
  public static function updateMaxOnline()
  {
    $controller = new \App\Http\Controllers\OnlineUserController();
    $controller->updateMaxOnline();
  }

  /**
   * Display a specific forum category and its subforums.
   *
   * @param  \App\Models\ForumCategory  $category
   * @return \Illuminate\Http\JsonResponse
   */
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

    return response()->json([
      'category' => $category
    ]);
  }

  /**
   * Display a specific subforum and its topics.
   *
   * @param  \App\Models\ForumCategory  $category
   * @param  \App\Models\ForumSubforum  $subforum
   * @return \Illuminate\Http\JsonResponse
   */
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
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      // For non-authenticated users, only show public posts
      $query->where('cyo_topics.privacy', 'public');
    }

    $topics = $query->get();

    return response()->json([
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

  /**
   * Show the form for creating a new topic in a subforum.
   *
   * @param  \App\Models\ForumSubforum  $subforum
   * @return \Illuminate\Http\JsonResponse
   */
  public function createTopic(ForumSubforum $subforum)
  {
    return response()->json([
      'subforum' => $subforum
    ]);
  }

  /**
   * Store a newly created topic in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\RedirectResponse
   */
  public function storeTopic(Request $request)
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required|string',
      'subforum_id' => 'required|exists:cyo_forum_subforums,id'
    ]);

    $topic = Topic::create([
      ...$validated,
      'cyo_topics.user_id' => auth()->id()
    ]);

    return redirect()->route('forum.topic.show', $topic)
      ->with('success', 'Chủ đề đã được tạo thành công.');
  }

  /**
   * Show the form for editing the specified topic.
   *
   * @param  \App\Models\Topic  $topic
   * @return \Illuminate\Http\JsonResponse
   */
  public function editTopic(Topic $topic)
  {
    $this->authorize('update', $topic);

    return response()->json([
      'topic' => $topic
    ]);
  }

  /**
   * Update the specified topic in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\Topic  $topic
   * @return \Illuminate\Http\RedirectResponse
   */
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

  /**
   * Remove the specified topic from storage.
   *
   * @param  \App\Models\Topic  $topic
   * @return \Illuminate\Http\RedirectResponse
   */
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
  //         'cyo_topics.user_id' => auth()->id()
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

  /**
   * Get subforums based on the user's role.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
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
          $query->where('cyo_topics.privacy', 'public')
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

  /**
   * Get all forum categories with their subforums.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getCategories()
  {
    $categories = ForumMainCategory::with([
      'subforums' => function ($query) {
        // Count topics and manually calculate comment count
        $query->withCount(['topics'])
          ->addSelect([
            'comment_count' => \DB::table('cyo_topic_comments')
              ->join('cyo_topics', 'cyo_topic_comments.topic_id', '=', 'cyo_topics.id')
              ->whereColumn('cyo_topics.subforum_id', 'cyo_forum_subforums.id')
              ->selectRaw('count(*)')
          ]);
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
        'slug' => $category->slug,
        'description' => $category->description,
        'seo_description' => $category->seo_description,
        'background_image' => $category->background_image,
        'created_at' => $category->created_at,
        'updated_at' => $category->updated_at,
        'subforums' => $category->subforums->map(function ($subforum) {
          $latestPost = $subforum->latestPublicTopic;
          return [
            'id' => $subforum->id,
            'main_category_id' => $subforum->main_category_id,
            'name' => $subforum->name,
            'slug' => $subforum->slug,
            'description' => $subforum->description,
            'seo_description' => $subforum->seo_description,
            'active' => $subforum->active,
            'pinned' => $subforum->pinned,
            'background_image' => $subforum->background_image,
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

  /**
   * Get the subforums of a specific main category.
   *
   * @param  \App\Models\ForumMainCategory  $mainCategory
   * @return \Illuminate\Support\Collection
   */
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
            $q->where('cyo_topics.privacy', 'public')
              ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
              ->orWhere(function ($subQ) use ($followingIds) {
                // Followers posts
                $subQ->where('cyo_topics.privacy', 'followers')
                  ->whereIn('cyo_topics.user_id', $followingIds);
              });
          });
        } else {
          // For non-authenticated users, only show public posts
          $query->where('cyo_topics.privacy', 'public');
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
        'seo_description' => $subforum->seo_description,
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

  /**
   * Store a newly created main category in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function storeCategory(Request $request)
  {
    $request->validate([
      'name' => 'required|string',
      'description' => 'nullable|string',
    ]);

    $category = ForumMainCategory::create($request->all());
    return response()->json($category);
  }

  /**
   * Store a newly created subforum in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Models\ForumMainCategory  $mainCategory
   * @return \Illuminate\Http\JsonResponse
   */
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

  /**
   * Get all pinned topics.
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getPinnedTopics()
  {
    return Topic::where('pinned', true)->get();
  }

  /**
   * Pin or unpin a topic.
   *
   * @param  \App\Models\Topic  $topic
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function pinTopic(Topic $topic, Request $request)
  {
    $topic->pinned = $request->input('pinned', false);
    $topic->save();

    return response()->json($topic);
  }

  /**
   * Display the specified topic.
   *
   * @param  string  $username
   * @param  string  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
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
      if ($post->user_id != $user->id) {
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
        'is_anonymous' => $comment->is_anonymous,
        'author' => [
          'id' => $comment->user->id,
          'username' => $comment->user->username,
          'email' => $comment->user->email,
          'profile_name' => $comment->user->profile->profile_name ?? null,
          'verified' => $comment->user->profile->verified == 1 ?? false ? true : false,
        ],
        'created_at' => $comment->created_at->diffForHumans(),
        'updated_at' => $comment->updated_at ? $comment->updated_at->diffForHumans() : null,
        'votes' => $comment->votes->map(fn($vote) => [
          'cyo_topics.user_id' => $vote->user_id,
          'username' => $vote->user->username,
          'vote_value' => $vote->vote_value,
        ]),
        'replies' => $comment->replies->map(function ($reply) {
          return [
            'id' => $reply->id,
            'content' => $reply->comment,
            'is_anonymous' => $reply->is_anonymous,
            'author' => [
              'id' => $reply->user->id,
              'username' => $reply->user->username,
              'email' => $reply->user->email,
              'profile_name' => $reply->user->profile->profile_name ?? null,
              'verified' => $reply->user->profile->verified == 1 ?? false ? true : false,
            ],
            'created_at' => $reply->created_at->diffForHumans(),
            'updated_at' => $reply->updated_at ? $reply->updated_at->diffForHumans() : null,
            'votes' => $reply->votes->map(fn($vote) => [
              'cyo_topics.user_id' => $vote->user_id,
              'username' => $vote->user->username,
              'vote_value' => $vote->vote_value,
            ]),
            'replies' => $reply->replies->map(function ($subReply) {
              return [
                'id' => $subReply->id,
                'content' => $subReply->comment,
                'is_anonymous' => $subReply->is_anonymous,
                'author' => [
                  'id' => $subReply->user->id,
                  'username' => $subReply->user->username,
                  'email' => $subReply->user->email,
                  'profile_name' => $subReply->user->profile->profile_name ?? null,
                  'verified' => $subReply->user->profile->verified == 1 ?? false ? true : false,
                ],
                'created_at' => $subReply->created_at->diffForHumans(),
                'updated_at' => $subReply->updated_at ? $subReply->updated_at->diffForHumans() : null,
                'votes' => $subReply->votes->map(fn($vote) => [
                  'cyo_topics.user_id' => $vote->user_id,
                  'username' => $vote->user->username,
                  'vote_value' => $vote->vote_value,
                ]),
              ];
            }),
          ];
        }),
      ];
    });

    // Get the first image URL for og:image
    $imageUrls = $post->getImageUrls()->map(function ($content) {
      return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
    })->all();

    $ogImage = !empty($imageUrls) ? $imageUrls[0] : asset('images/cyo_thumbnail.png');

    $isSaved = false;
    if (Auth::check()) {
      $isSaved = UserSavedTopic::where('cyo_topics.user_id', Auth::id())
        ->where('topic_id', $post->id)
        ->exists();
    }

    return response()->json([
      'post' => [
        'id' => $post->id,
        'title' => $post->title,
        'content' => $post->content_html,
        'image_urls' => $imageUrls,
        'document_urls' => $post->getDocuments()->map(function ($content) {
          return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
        })->all(),
        'document_sizes' => $post->getDocuments()->map(function ($content) {
          return $content->file_size;
        })->all(),
        'votes' => $post->votes->map(function ($vote) {
          return [
            'username' => $vote->user->username, // Assuming votes relation includes the user
            'vote_value' => $vote->vote_value,
            'created_at' => $vote->created_at ? $vote->created_at->toISOString() : null,
            'updated_at' => $vote->updated_at ? $vote->updated_at->toISOString() : null,
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
        'is_saved' => $isSaved,
        'comments' => $formattedComments,
      ],
      'ogImage' => $ogImage,
      'comments' => $formattedComments
    ]);
  }

  /**
   * Get all posts for a specific subforum.
   *
   * @param  \App\Models\ForumSubforum  $subforum
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSubforumPosts(ForumSubforum $subforum)
  {
    $query = $subforum->topics()
      ->with(['user.profile', 'comments'])
      ->withCount(['comments as reply_count', 'views'])
      ->leftJoin('cyo_topic_comments', function ($join) {
        $join->on('cyo_topics.id', '=', 'cyo_topic_comments.topic_id')
          ->whereRaw('cyo_topic_comments.created_at = (SELECT MAX(created_at) FROM cyo_topic_comments WHERE topic_id = cyo_topics.id)');
      })
      ->orderBy('pinned', 'desc')
      ->orderByRaw('COALESCE(cyo_topic_comments.created_at, cyo_topics.created_at) DESC');

    // Apply privacy filtering
    if (auth()->check()) {
      $userId = auth()->id();
      $followingIds = \App\Models\Follower::where('follower_id', $userId)
        ->pluck('followed_id')
        ->toArray();

      $query->where(function ($q) use ($userId, $followingIds) {
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      // For non-authenticated users, only show public posts
      $query->where('cyo_topics.privacy', 'public');
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
    $query = Topic::select(['id', 'title', 'created_at', 'cyo_topics.user_id', 'anonymous'])
      ->with(['user.profile'])
      ->where('hidden', false)
      ->orderBy('created_at', 'desc')
      ->take(10);

    // Apply privacy filtering
    if (auth()->check()) {
      $userId = auth()->id();
      $followingIds = \App\Models\Follower::where('follower_id', $userId)
        ->pluck('followed_id')
        ->toArray();

      $query->where(function ($q) use ($userId, $followingIds) {
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            // Followers posts
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      $query->where('cyo_topics.privacy', 'public');
    }

    return $query->get();
  }

  // Lấy các bài viết có lượt xem nhiều nhất
  private function getMostViewedPosts()
  {
    $query = Topic::select(['id', 'title', 'created_at', 'cyo_topics.user_id', 'anonymous'])
      ->with(['user.profile'])
      ->where('hidden', false)
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
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            // Followers posts
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      $query->where('cyo_topics.privacy', 'public');
    }

    return $query->get();
  }

  // Lấy các bài viết có lượt xem và lượt tương tác (bình luận, like) nhiều nhất
  private function getMostEngagedPosts()
  {
    $query = Topic::select(['id', 'title', 'created_at', 'cyo_topics.user_id', 'anonymous'])
      ->with(['user.profile'])
      ->where('hidden', false)
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
        $q->where('cyo_topics.privacy', 'public')
          ->orWhere('cyo_topics.user_id', $userId) // User's own posts (including private ones)
          ->orWhere(function ($subQ) use ($followingIds) {
            // Followers posts
            $subQ->where('cyo_topics.privacy', 'followers')
              ->whereIn('cyo_topics.user_id', $followingIds);
          });
      });
    } else {
      $query->where('cyo_topics.privacy', 'public');
    }

    return $query->get();
  }

  /**
   * Format latest posts to only include title, created_at, and author name
   *
   * @param \Illuminate\Database\Eloquent\Collection $posts
   * @return \Illuminate\Support\Collection
   */
  private function formatLatestPosts($posts)
  {
    return $posts->map(function ($post) {
      return [
        'id' => $post->id,
        'title' => $post->title,
        'created_at' => $post->created_at,
        'anonymous' => (bool) $post->anonymous,
        'author_name' => $post->anonymous
          ? 'Người dùng ẩn danh'
          : ($post->user->profile->profile_name ?? $post->user->username),
        'username' => $post->anonymous ? null : $post->user->username
      ];
    });
  }

  /**
   * Format main categories to only include category name and subforum name
   *
   * @param \Illuminate\Database\Eloquent\Collection $categories
   * @return \Illuminate\Support\Collection
   */
  private function formatMainCategories($categories)
  {
    return $categories->map(function ($category) {
      return [
        'name' => $category->name,
        'slug' => $category->slug,
        'subforums' => $category->subforums->map(function ($subforum) {
          return [
            'name' => $subforum->name,
            'slug' => $subforum->slug,
            'topics_count' => $subforum->topics_count,
            'comments_count' => $subforum->comments_count,
            'latest_topic' => ($subforum->latest_public_topic && $subforum->latest_public_topic->title) ? [
              'id' => $subforum->latest_public_topic->id,
              'title' => $subforum->latest_public_topic->title,
              'anonymous' => (bool) $subforum->latest_public_topic->anonymous,
              'username' => $subforum->latest_public_topic->anonymous ? null : $subforum->latest_public_topic->user->username,
              'author_name' => $subforum->latest_public_topic->anonymous
                ? 'Người dùng ẩn danh'
                : ($subforum->latest_public_topic->user->profile->profile_name ?? $subforum->latest_public_topic->user->username),
              'verified' => $subforum->latest_public_topic->anonymous ? false : (bool) $subforum->latest_public_topic->user->profile->verified,
              'created_at' => $subforum->latest_public_topic->created_at
            ] : null
          ];
        })
      ];
    });
  }

  /**
   * Get the URL for a specific post by title, user_id, and subforum_id
   * Used to retrieve forum rules and other important posts after ID scrambling
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPostUrl(Request $request)
  {
    // Get parameters from query string
    $title = $request->query('title');
    $userId = $request->query('user_id');
    $subforumId = $request->query('subforum_id');

    // Validate parameters
    if (empty($title) || empty($userId) || empty($subforumId)) {
      return response()->json([
        'success' => false,
        'message' => 'Thiếu tham số bắt buộc: title, user_id, subforum_id'
      ], 400);
    }

    $topic = Topic::where('subforum_id', $subforumId)
      ->where('user_id', $userId)
      ->where('title', $title)
      ->first();

    if (!$topic) {
      return response()->json([
        'success' => false,
        'message' => 'Bài viết không tồn tại'
      ], 404);
    }

    $username = $topic->anonymous ? 'anonymous' : $topic->user->username;
    $titleSlug = str()->slug($topic->title, '-') ?: 'untitled';
    $correctSlug = $topic->id . '-' . $titleSlug;

    $url = url()->route('posts.show', [
      'username' => $username,
      'id' => $correctSlug
    ]);

    return response()->json([
      'success' => true,
      'url' => $url,
      'post_id' => $topic->id,
      'username' => $username,
      'slug' => $correctSlug
    ]);
  }
}
