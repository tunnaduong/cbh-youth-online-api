<?php

namespace App\Console\Commands;

use App\Models\TopicComment;
use Illuminate\Console\Command;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

class ConvertMarkdownComments extends Command
{
  /**
   * Tên lệnh khi gọi Artisan
   */
  protected $signature = 'comments:convert-markdown';

  /**
   * Mô tả command
   */
  protected $description = 'Chuyển đổi toàn bộ nội dung comment từ Markdown sang HTML (lưu vào cột comment_html)';

  public function handle()
  {
    $config = [
      'renderer' => [
        'soft_break' => "<br>\n",
      ],
    ];

    $converter = new CommonMarkConverter($config);
    $converter->getEnvironment()->addExtension(new AutolinkExtension());
    $comments = TopicComment::all();
    $count = 0;

    foreach ($comments as $comment) {
      // Chuyển markdown -> HTML
      $html = $converter->convert($comment->comment)->getContent();

      // Ghi đè lên cột comment_html
      $comment->comment_html = $html;

      $comment->save();
      $count++;
    }

    $this->info("✅ Đã chuyển đổi {$count} comment vào cột comment_html.");
  }
}
