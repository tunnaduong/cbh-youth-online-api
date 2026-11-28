<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_ad_slots', function (Blueprint $table) {
      if (!Schema::hasColumn('cyo_ad_slots', 'html_code')) {
        $table->text('html_code')->nullable()->after('alt_text');
      }
      $table->string('image_url', 500)->nullable()->change();
    });

    if (!DB::table('cyo_ad_slots')->where('placement', 'mobile-feed-inline')->exists()) {
      DB::table('cyo_ad_slots')->insert([
        'name' => 'Adsterra 300x250 - Bảng tin app mobile',
        'placement' => 'mobile-feed-inline',
        'image_url' => null,
        'link_url' => null,
        'alt_text' => 'Được tài trợ',
        'html_code' => <<<'HTML'
<script>
  atOptions = {
    'key' : 'ddebc5ab971d5273d0fb5a79cbbfe198',
    'format' : 'iframe',
    'height' : 250,
    'width' : 300,
    'params' : {}
  };
</script>
<script src="https://www.highrevenueformat.com/ddebc5ab971d5273d0fb5a79cbbfe198/invoke.js"></script>
HTML,
        'is_active' => true,
        'priority' => 10,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }

    if (!DB::table('cyo_ad_slots')->where('placement', 'mobile-post-comments')->exists()) {
      DB::table('cyo_ad_slots')->insert([
        'name' => 'Adsterra Native Banner - Bình luận bài viết app mobile',
        'placement' => 'mobile-post-comments',
        'image_url' => null,
        'link_url' => null,
        'alt_text' => 'Nội dung được tài trợ',
        'html_code' => <<<'HTML'
<script async="async" data-cfasync="false" src="https://pl32171519.profitableratecpmnetwork.com/d75e1be3578406d08612b5cc3623fa29/invoke.js"></script>
<div id="container-d75e1be3578406d08612b5cc3623fa29"></div>
HTML,
        'is_active' => true,
        'priority' => 10,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }
  }

  public function down(): void
  {
    DB::table('cyo_ad_slots')
      ->whereIn('placement', ['mobile-feed-inline', 'mobile-post-comments'])
      ->whereIn('name', [
        'Adsterra 300x250 - Bảng tin app mobile',
        'Adsterra Native Banner - Bình luận bài viết app mobile',
      ])
      ->delete();

    Schema::table('cyo_ad_slots', function (Blueprint $table) {
      if (Schema::hasColumn('cyo_ad_slots', 'html_code')) {
        $table->dropColumn('html_code');
      }
    });
  }
};
