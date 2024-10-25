<?php

namespace App\Http\Controllers;

use App\Models\ForumMainCategory;
use App\Models\ForumSubforum;
use App\Models\Topic;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    // Get all main categories
    public function getCategories()
    {
        return ForumMainCategory::with('subforums')->get();
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
