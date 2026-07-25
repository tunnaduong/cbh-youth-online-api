<?php

namespace App\Console\Commands;

use App\Models\Topic;
use App\Services\HashtagService;
use Illuminate\Console\Command;

class BackfillHashtags extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'hashtags:backfill';

  /**
   * Mô tả command
   */
  protected $description = 'Quét lại content_html của toàn bộ bài viết cũ, gắn link cho #hashtag và đồng bộ vào bảng cyo_hashtags';

  public function handle()
  {
    $count = 0;
    $withHashtags = 0;

    Topic::whereNotNull('content_html')->chunkById(100, function ($posts) use (&$count, &$withHashtags) {
      foreach ($posts as $post) {
        $result = HashtagService::linkify($post->content_html);

        // Avoid an UPDATE when the post has no hashtags at all.
        if ($result['html'] !== $post->content_html) {
          $post->content_html = $result['html'];
          $post->save();
        }

        if (!empty($result['tags'])) {
          HashtagService::syncTopicHashtags($post, $result['tags']);
          $withHashtags++;
        }

        $count++;
      }
    });

    $this->info("✅ Đã quét {$count} bài viết, tìm thấy hashtag trong {$withHashtags} bài.");
  }
}
