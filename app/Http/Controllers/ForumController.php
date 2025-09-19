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
        $query->withCount(['topics', 'comments'])
          ->with([
            'topics' => function ($query) {
              $query->select('cyo_topics.*')
                ->join(DB::raw('(SELECT subforum_id, MAX(created_at) as max_created_at FROM cyo_topics GROUP BY subforum_id) as latest'), function ($join) {
                  $join->on('cyo_topics.subforum_id', '=', 'latest.subforum_id')
                    ->on('cyo_topics.created_at', '=', 'latest.max_created_at');
                })
                ->with('user.profile');
            }
          ]);
      }
    ])
      ->orderBy('arrange')
      ->get();

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
    $posts = Topic::with(['user.profile', 'comments', 'votes'])
      ->withCount(['comments as reply_count', 'views'])
      ->orderBy('created_at', 'desc')
      ->where('hidden', false)
      ->get()
      ->map(function ($post) {
        return [
          'id' => $post->id,
          'title' => $post->title,
          'content' => $post->content_html,
          'image_urls' => $post->getImageUrls()->map(function ($content) {
            return 'https://api.chuyenbienhoa.com' . Storage::url($content->file_path);
          })->all(),
          'author' => [
            'id' => $post->user->id,
            'username' => $post->user->username,
            'email' => $post->user->email,
            'profile_name' => $post->user->profile->profile_name ?? null,
            'verified' => $post->user->profile->verified == 1 ?? false ? true : false,
          ],
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
      });

    return Inertia::render('Feed/index', [
      'posts' => $posts
    ]);
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
            'latestTopic' => function ($q) {
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
    $topics = $subforum->topics()
      ->with(['user.profile', 'comments'])
      ->withCount(['comments as reply_count', 'views'])
      ->orderBy('pinned', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();

    return Inertia::render('Forum/Subforum', [
      'category' => $category,
      'subforum' => $subforum,
      'topics' => $topics->map(function ($topic) {
        return [
          'id' => $topic->id,
          'title' => $topic->title,
          'content' => Str::limit($topic->description, 100),
          'pinned' => $topic->pinned,
          'created_at' => $topic->created_at->diffForHumans(),
          'updated_at' => $topic->updated_at->diffForHumans(),
          'reply_count' => $this->roundToNearestFive($topic->reply_count),
          'view_count' => $topic->views_count,
          'author' => [
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
    return Inertia::render('Forum/Topic/Create', [
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

    return Inertia::render('Forum/Topic/Show', [
      'topic' => $topic,
      'replies' => $replies
    ]);
  }

  public function editTopic(Topic $topic)
  {
    $this->authorize('update', $topic);

    return Inertia::render('Forum/Topic/Edit', [
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
          $query->latest('created_at')->with(['user.profile']);
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
          $query->latest('created_at')->with(['user.profile']);
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
        $query->withCount('topics')
          ->withCount([
            'topics as comment_count' => function ($query) {
              $query->leftJoin('cyo_topic_comments', 'cyo_topics.id', '=', 'cyo_topic_comments.topic_id')
                ->selectRaw('IFNULL(count(cyo_topic_comments.id), 0)');
            }
          ])
          ->with([
            'topics' => function ($query) {
              $query->latest('created_at')->with(['user.profile'])->limit(1);
            }
          ]);
      }
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
          $latestPost = $subforum->latestTopic;
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
    $user = AuthAccount::where('username', $username)->firstOrFail();
    $post = Topic::with(['author.profile', 'comments.user.profile', 'user', 'votes.user', 'cdnUserContent'])
      ->withCount(['comments as reply_count', 'views'])
      ->where('user_id', $user->id)
      ->findOrFail($id);

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
        'reply_count' => $this->roundToNearestFive($post->reply_count),
        'view_count' => $post->views_count,
        'created_at' => $post->created_at->diffForHumans(),
        'author' => [
          'username' => $post->author->username,
          'profile_name' => $post->author->profile->profile_name ?? null,
          'verified' => $post->user->profile->verified == 1 ?? false ? true : false,
        ],
        'comments' => $formattedComments,
      ]
    ]);
  }

  public function getSubforumPosts(ForumSubforum $subforum)
  {
    $topics = $subforum->topics()
      ->with(['user.profile', 'comments'])
      ->withCount(['comments as reply_count', 'views'])
      ->orderBy('pinned', 'desc')
      ->orderBy('updated_at', 'desc')
      ->get();

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
          'created_at' => $topic->created_at->diffForHumans(),
          'updated_at' => $topic->updated_at->diffForHumans(),
          'reply_count' => $this->roundToNearestFive($topic->reply_count),
          'view_count' => $topic->views_count,
          'author' => [
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
    return Topic::with('user.profile')
      ->where('hidden', false)
      ->orderBy('created_at', 'desc')
      ->take(10)
      ->get();
  }

  // Lấy các bài viết có lượt xem nhiều nhất
  private function getMostViewedPosts()
  {
    return Topic::with('user.profile')
      ->where('hidden', false)
      ->withCount('views')
      ->orderBy('views_count', 'desc')
      ->take(10)
      ->get();
  }

  // Lấy các bài viết có lượt xem và lượt tương tác (bình luận, like) nhiều nhất
  private function getMostEngagedPosts()
  {
    return Topic::with('user.profile')
      ->where('hidden', false)
      ->withCount(['comments', 'votes'])
      ->orderByRaw('(comments_count + votes_count) DESC')
      ->take(10)
      ->get();
  }
}
