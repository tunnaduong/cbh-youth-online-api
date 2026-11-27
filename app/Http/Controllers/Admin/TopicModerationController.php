<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TopicModerationController extends Controller
{
  /**
   * Toggle whether a topic is hidden from the homepage "Bài viết mới nhất" feed.
   * The post remains visible everywhere else (profile, forum, search, feed).
   */
  public function toggleHideFromLatest(Request $request, int $id)
  {
    $topic = Topic::findOrFail($id);
    $topic->hidden_from_latest = !$topic->hidden_from_latest;
    $topic->save();

    if (!Cache::has('feed_version')) {
      Cache::forever('feed_version', 1);
    }
    Cache::increment('feed_version');

    return response()->json([
      'hidden_from_latest' => (bool) $topic->hidden_from_latest,
      'message' => $topic->hidden_from_latest
        ? 'Đã ẩn bài viết khỏi trang chủ.'
        : 'Đã hiện lại bài viết trên trang chủ.',
    ]);
  }
}
