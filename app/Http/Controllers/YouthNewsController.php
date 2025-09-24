<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YouthNewsController extends Controller
{
    /**
     * Display a listing of youth news.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $youthNews = Topic::with(['user.profile', 'comments', 'votes'])
            ->withCount(['comments as reply_count', 'views'])
            ->orderBy('created_at', 'desc')
            ->where('hidden', false)
            ->where('subforum_id', 32)
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

        return Inertia::render('YouthNews/Index', [
            'youthNews' => $youthNews
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
}
