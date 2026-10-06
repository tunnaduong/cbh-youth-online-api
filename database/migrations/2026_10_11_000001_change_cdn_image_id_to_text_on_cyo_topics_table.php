<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  // cdn_image_id là danh sách id cách nhau bởi dấu phẩy; varchar(255) chỉ chứa được
  // khoảng 50 ảnh. Index còn sót lại từ khoá ngoại cũ không dùng được cho kiểu dữ
  // liệu này và MySQL không cho index cột TEXT nếu thiếu độ dài, nên bỏ luôn.
  private const INDEX = 'cyo_topics_cdn_image_id_foreign';

  public function up(): void
  {
    if (DB::select('SHOW INDEX FROM cyo_topics WHERE Key_name = ?', [self::INDEX])) {
      DB::statement('ALTER TABLE cyo_topics DROP INDEX ' . self::INDEX);
    }
    DB::statement('ALTER TABLE cyo_topics MODIFY cdn_image_id TEXT NULL');
  }

  public function down(): void
  {
    DB::statement('ALTER TABLE cyo_topics MODIFY cdn_image_id VARCHAR(255) NULL');
    DB::statement('ALTER TABLE cyo_topics ADD INDEX ' . self::INDEX . ' (cdn_image_id)');
  }
};
