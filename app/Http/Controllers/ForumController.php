<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use App\Models\ForumSubforum;
use App\Models\ForumMainCategory;
use Illuminate\Support\Facades\Log;
use App\Models\ForumCategory;
use App\Models\Reply;
use Inertia\Inertia;

class ForumController extends Controller
{
    public function index()
    {
        $categories = ForumCategory::with(['subforums' => function ($query) {
            $query->withCount('topics');
        }])
        ->orderBy('arrange')
        ->get();

        return Inertia::render('Forum/Index', [
            'categories' => $categories
        ]);
    }

    public function category(ForumCategory $category)
    {
        $category->load(['subforums' => function ($query) {
            $query->withCount('topics');
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

    public function storeReply(Request $request, Topic $topic)
    {
        $validated = $request->validate([
            'content' => 'required|string'
        ]);

        Reply::create([
            ...$validated,
            'topic_id' => $topic->id,
            'user_id' => auth()->id()
        ]);

        return redirect()->route('forum.topic.show', $topic)
            ->with('success', 'Trả lời đã được gửi thành công.');
    }

    public function updateReply(Request $request, Reply $reply)
    {
        $this->authorize('update', $reply);

        $validated = $request->validate([
            'content' => 'required|string'
        ]);

        $reply->update($validated);

        return redirect()->route('forum.topic.show', $reply->topic)
            ->with('success', 'Trả lời đã được cập nhật thành công.');
    }

    public function destroyReply(Reply $reply)
    {
        $this->authorize('delete', $reply);

        $topic = $reply->topic;
        $reply->delete();

        return redirect()->route('forum.topic.show', $topic)
            ->with('success', 'Trả lời đã được xóa thành công.');
    }

    // Get all subforums and filter by role (user, admin, teacher) of current logged in user
    public function getSubforumsByRole(Request $request)
    {
        // Check if a user is authenticated
        if (auth()->check()) {
            $user = auth()->user();
            $role = $user->role; // Assuming 'role' is a field in your user model

            // Get all subforums
            $subforums = ForumSubforum::with(['mainCategory', 'topics' => function ($query) {
                $query->latest('created_at')->with(['user.profile']);
            }])
            ->where('active', true)
            ->get();

            // Filter subforums based on user role
            if ($role === 'admin') {
                // Admin can see all subforums with role_restriction = 'user' or 'admin'
                $subforums = $subforums->filter(function ($subforum) {
                    return in_array($subforum->role_restriction, ['user', 'admin']);
                });
            } elseif ($role === 'teacher') {
                // Teacher can see all subforums with role_restriction = 'user' or 'teacher'
                $subforums = $subforums->filter(function ($subforum) {
                    return in_array($subforum->role_restriction, ['user', 'teacher']);
                });
            } else {
                // Regular users can see only subforums with role_restriction = 'user'
                $subforums = $subforums->filter(function ($subforum) {
                    return $subforum->role_restriction === 'user';
                });
            }
        } else {
            // If user is not logged in, show all subforums
            $subforums = ForumSubforum::with(['mainCategory', 'topics' => function ($query) {
                $query->latest('created_at')->with(['user.profile']);
            }])
            ->where('active', true)
            ->get();
        }

        // Order the subforums by the arrange field of their main category
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

    // Get all main categories
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

        // Format the response
        $categories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'subforums' => $category->subforums->map(function ($subforum) {
                    $latestPost = $subforum->latestTopic; // Use the latestTopic relationship
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


    // Get all subforums under a category
    public function getSubforums(ForumMainCategory $mainCategory)
    {
        $subforums = $mainCategory->subforums()->where('active', true)->withCount('topics')->with(['topics' => function ($query) {
            $query->latest('created_at');
        }])->get();

        return $subforums->map(function ($subforum) use ($mainCategory) {
            $latestPost = $subforum->topics->first(); // Get the latest topic for each subforum

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

    // Create a new main category
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $category = ForumMainCategory::create($request->all());
        return response()->json($category);
    }

    // Create a new subforum under a main category
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

    // Get pinned topics
    public function getPinnedTopics()
    {
        return Topic::where('pinned', true)->get();
    }

    // Pin or unpin a topic
    public function pinTopic(Topic $topic, Request $request)
    {
        $topic->pinned = $request->input('pinned', false);
        $topic->save();

        return response()->json($topic);
    }
}
