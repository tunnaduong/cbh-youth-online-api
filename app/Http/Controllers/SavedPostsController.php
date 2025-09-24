<?php

namespace App\Http\Controllers;

use App\Models\UserSavedTopic;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SavedPostsController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $savedTopics = UserSavedTopic::where('user_id', $userId)
            ->with([
                'topic' => function ($query) {
                    $query->withCount(['views', 'comments'])
                        ->withSum('votes', 'vote_value')
                        ->with(['author.profile']);
                }
            ])
            ->latest()
            ->get()
            ->map(function ($savedTopic) {
                $topic = $savedTopic->topic;
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'image_urls' => $topic->getImageUrls(),
                    'author' => [
                        'username' => $topic->author->username,
                        'profile_name' => $topic->author->profile->profile_name ?? null,
                        'verified' => $topic->author->profile->verified ?? false
                    ],
                    'stats' => [
                        'views' => $topic->views_count,
                        'comments' => $topic->comments_count,
                        'votes' => $topic->votes_sum_vote_value ?? 0
                    ],
                    'created_at' => $topic->created_at->diffForHumans()
                ];
            });

        return Inertia::render('SavedPosts/Index', [
            'savedTopics' => $savedTopics
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'topic_id' => 'required|exists:cyo_topics,id',
        ]);

        $userId = Auth::id();

        // Check if already saved
        $exists = UserSavedTopic::where('topic_id', $request->topic_id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return back()->with('error', 'This topic is already saved.');
        }

        UserSavedTopic::create([
            'user_id' => $userId,
            'topic_id' => $request->topic_id,
        ]);

        return back()->with('success', 'Topic saved successfully.');
    }

    public function destroy($savedPost)
    {
        $userId = Auth::id();

        $savedTopic = UserSavedTopic::where('topic_id', $savedPost)
            ->where('user_id', $userId)
            ->first();

        if (!$savedTopic) {
            return back()->with('error', 'Saved topic not found.');
        }

        $savedTopic->delete();

        return back()->with('success', 'Topic removed from saved items.');
    }
}
