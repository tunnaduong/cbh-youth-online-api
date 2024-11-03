<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use App\Models\ForumSubforum;
use App\Models\ForumMainCategory;
use Illuminate\Support\Facades\Log;

class ForumController extends Controller
{
    // Get all main categories
    public function getCategories()
    {
        $categories = ForumMainCategory::with(['subforums' => function ($query) {
            // Eager load post count and latest topic for each subforum
            $query->withCount('topics')
                ->with(['topics' => function ($query) {
                    $query->latest('created_at')->first()->with(['user.profile']);
                }]); // Use the new relationship here
        }])->get();

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
        return $mainCategory->subforums()->where('active', true)->get();
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
