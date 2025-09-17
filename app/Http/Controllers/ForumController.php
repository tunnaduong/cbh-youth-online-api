<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use App\Models\ForumSubforum;
use App\Models\ForumMainCategory;
use App\Models\AuthAccount;
use App\Models\TopicComment;
use Illuminate\Support\Facades\Log;
use App\Models\ForumCategory;
use App\Models\Reply;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForumController extends Controller
{
  public function index()
  {
    $mainCategories = ForumCategory::with(['subforums' => function ($query) {
      $query->withCount(['topics', 'comments'])
        ->with(['topics' => function ($query) {
          $query->select('cyo_topics.*')
            ->join(DB::raw('(SELECT subforum_id, MAX(created_at) as max_created_at FROM cyo_topics GROUP BY subforum_id) as latest'), function ($join) {
              $join->on('cyo_topics.subforum_id', '=', 'latest.subforum_id')
                ->on('cyo_topics.created_at', '=', 'latest.max_created_at');
            })
            ->with('user.profile');
        }]);
    }])
      ->orderBy('arrange')
      ->get();

    $latestPosts = Topic::with('user.profile')
      ->orderBy('created_at', 'desc')
      ->where('hidden', false)
      ->take(10)
      ->get();

    $userCount = AuthAccount::count();
    $postCount = Topic::count();
    $commentCount = TopicComment::count();

    $record = DB::table('cyo_online_record')->first();

    $onlineUsers = DB::table('cyo_online_users')
      ->where('last_activity', '>=', now()->subMinutes(5))
      ->get();

    $stats = (object)[
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
    $category->load(['subforums' => function ($query) {
      $query->withCount(['topics', 'comments']);
    }]);

    return Inertia::render('Forum/Category', [
      'category' => $category
    ]);
  }

  public function subforum(ForumSubforum $subforum)
  {
    $topics = $subforum->topics()
      ->with('user')
      ->withCount('replies')
      ->orderBy('pinned', 'desc')
      ->orderBy('updated_at', 'desc')
      ->paginate(20);

    return Inertia::render('Forum/Subforum', [
      'subforum' => $subforum,
      'topics' => $topics
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

      $subforums = ForumSubforum::with(['mainCategory', 'topics' => function ($query) {
        $query->latest('created_at')->with(['user.profile']);
      }])
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
      $subforums = ForumSubforum::with(['mainCategory', 'topics' => function ($query) {
        $query->latest('created_at')->with(['user.profile']);
      }])
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
    $categories = ForumMainCategory::with(['subforums' => function ($query) {
      $query->withCount('topics')
        ->withCount(['topics as comment_count' => function ($query) {
          $query->leftJoin('cyo_topic_comments', 'cyo_topics.id', '=', 'cyo_topic_comments.topic_id')
            ->selectRaw('IFNULL(count(cyo_topic_comments.id), 0)');
        }])
        ->with(['topics' => function ($query) {
          $query->latest('created_at')->with(['user.profile'])->limit(1);
        }]);
    }])
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
    $subforums = $mainCategory->subforums()->where('active', true)->withCount('topics')->with(['topics' => function ($query) {
      $query->latest('created_at');
    }])->get();

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
    $post = Topic::with(['author.profile', 'comments.user.profile'])
      ->where('user_id', $user->id)
      ->findOrFail($id);

    return Inertia::render('Posts/Show', [
      'post' => [
        'id' => $post->id,
        'title' => $post->title,
        'content' => $post->content,
        'created_at' => $post->created_at->diffForHumans(),
        'author' => [
          'username' => $post->author->username,
          'profile_name' => $post->author->profile->profile_name ?? null,
        ],
        'comments' => $post->comments->map(function ($comment) {
          return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at->diffForHumans(),
            'user' => [
              'username' => $comment->user->username,
              'profile_name' => $comment->user->profile->profile_name ?? null,
            ]
          ];
        })
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
          'reply_count' => $topic->reply_count,
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
            'user' => [
              'username' => $topic->comments->sortByDesc('created_at')->first()->user->username,
              'profile_name' => $topic->comments->sortByDesc('created_at')->first()->user->profile->profile_name ?? null
            ]
          ] : null
        ];
      })
    ]);
  }
}
