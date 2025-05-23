<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\Topic;
use App\Models\Follower;
use App\Models\AuthAccount;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    /**
     * Search across all content types
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        $type = $request->input('type', 'all'); // all, users, posts, stories
        $limit = $request->input('limit', 10);

        if (empty($query)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search query is required'
            ], 422);
        }

        $results = [];

        switch ($type) {
            case 'users':
                $results = $this->searchUsers($query, $limit);
                break;
            case 'posts':
                $results = $this->searchPosts($query, $limit);
                break;
            default:
                // Search all content types
                $results = [
                    'users' => $this->searchUsers($query, $limit),
                    'posts' => $this->searchPosts($query, $limit),
                ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    /**
     * Search for users
     */
    private function searchUsers($query, $limit)
    {
        return AuthAccount::where(function ($q) use ($query) {
            $q->where('username', 'LIKE', "%{$query}%")
                ->orWhereHas('profile', function ($q) use ($query) {
                    $q->where('profile_name', 'LIKE', "%{$query}%")
                        ->orWhere('bio', 'LIKE', "%{$query}%");
                });
        })
        ->with(['profile'])
        ->limit($limit)
        ->get()
        ->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'profile_name' => $user->profile->profile_name ?? $user->username,
                'bio' => $user->profile->bio ?? null,
            ];
        });
    }

    /**
     * Search for posts
     */
    private function searchPosts($query, $limit)
    {
        return Topic::where(function ($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%");
        })
        ->with(['user.profile', 'cdnUserContent'])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'content_preview' => Str::limit(strip_tags($post->content), 150),
                'image_url' => $post->cdnUserContent ? Storage::url($post->cdnUserContent->file_path) : null,
                'created_at' => $post->created_at->diffForHumans(),
                'author' => [
                    'id' => $post->user->id,
                    'username' => $post->user->username,
                    'profile_name' => $post->user->profile->profile_name ?? $post->user->username,
                ],
                'stats' => [
                    'views' => $post->views_count,
                    'comments' => $post->comments_count,
                    'likes' => $post->likes_count
                ]
            ];
        });
    }
}
