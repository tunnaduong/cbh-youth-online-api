<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSavedTopic;

/**
 * Handles the retrieval and display of youth news articles.
 */
class YouthNewsController extends Controller
{
  /**
   * Display a listing of youth news.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function index()
  {
    $query = $this->buildYouthNewsQuery();

    // Paginate youth news
    $paginatedNews = $query->paginate(10)->through(function ($topic) {
      return $this->formatYouthNewsData($topic);
    });




    return response()->json($paginatedNews);
  }

  /**
   * Build the base query for fetching youth news.
   *
   * @return \Illuminate\Database\Eloquent\Builder
   */
  private function buildYouthNewsQuery()
  {
    return Topic::with(['user.profile', 'comments', 'votes'])
      ->withCount(['comments as reply_count', 'views'])
      ->orderBy('created_at', 'desc')
      ->where('hidden', false)
      ->where('subforum_id', 32);
  }

  /**
   * Format the raw post data for a consistent response.
   *
   * @param  \App\Models\Topic  $post
   * @return array
   */
  private function formatYouthNewsData($post)
  {
    $isSaved = false;
    if (Auth::check()) {
      $isSaved = UserSavedTopic::where('user_id', Auth::id())
        ->where('topic_id', $post->id)
        ->exists();
    }

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
   * Round a number down to the nearest multiple of five for display purposes.
   *
   * @param  int  $count
   * @return string
   */
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
