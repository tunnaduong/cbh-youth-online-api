<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Topic;
use League\CommonMark\CommonMarkConverter;

class ConvertMarkdownPosts extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'posts:convert-markdown {--replace : Ghi đè nội dung gốc thay vì lưu sang cột khác}';

  /**
   * Mô tả command
   */
  protected $description = 'Chuyển đổi toàn bộ nội dung post từ Markdown sang HTML';

  public function handle()
  {
    $converter = new CommonMarkConverter([
      'renderer' => [
        'soft_break' => "<br>\n", // 1 enter = <br>
      ],
    ]);

    $posts = Topic::all();
    $count = 0;

    foreach ($posts as $post) {
      // Chuyển markdown -> HTML
      $html = $converter->convert($post->description)->getContent();

      if ($this->option('replace')) {
        // Ghi đè lên cột description
        $post->description = $html;
      } else {
        // Lưu sang cột content_html (cần có sẵn trong DB)
        $post->content_html = $html;
      }

      $post->save();
      $count++;
    }

    $this->info("✅ Đã chuyển đổi {$count} bài viết.");
  }
}
