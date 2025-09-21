<?php

namespace App\Console\Commands;

use App\Models\Recording;
use Illuminate\Console\Command;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

class ConvertMarkdownRecordings extends Command
{
    /**
     * Tên lệnh khi gọi Artisan
     */
    protected $signature = 'recordings:convert-markdown {--replace : Ghi đè nội dung gốc thay vì lưu sang cột khác}';

    /**
     * Mô tả command
     */
    protected $description = 'Chuyển đổi toàn bộ nội dung recording từ Markdown sang HTML';

    public function handle()
    {
        $config = [
            'renderer' => [
                'soft_break' => "<br>\n",
            ],
        ];

        $converter = new CommonMarkConverter($config);
        $converter->getEnvironment()->addExtension(new AutolinkExtension());

        $recordings = Recording::all();
        $count = 0;

        $this->info("🔄 Bắt đầu chuyển đổi {$recordings->count()} bản ghi...");

        foreach ($recordings as $recording) {
            // Chuyển markdown -> HTML
            $html = $converter->convert($recording->description)->getContent();

            if ($this->option('replace')) {
                // Ghi đè lên cột description
                $recording->description = $html;
            } else {
                // Lưu sang cột content_html
                $recording->content_html = $html;
            }

            $recording->save();
            $count++;

            // Hiển thị progress
            if ($count % 10 == 0) {
                $this->info("✅ Đã chuyển đổi {$count} bản ghi...");
            }
        }

        $this->info("🎉 Hoàn thành! Đã chuyển đổi {$count} bản ghi từ Markdown sang HTML.");

        if (!$this->option('replace')) {
            $this->info("💡 Sử dụng --replace để ghi đè nội dung gốc thay vì lưu vào content_html");
        }
    }
}
