<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    if (DB::table('cyo_ad_slots')->where('placement', 'mobile-home-header')->exists()) {
      return;
    }

    DB::table('cyo_ad_slots')->insert([
      'name' => 'SEELI - Header trang chủ mobile',
      'placement' => 'mobile-home-header',
      'image_url' => 'https://chuyenbienhoa.com/images/ads/seeli-home-banner.jpg',
      'link_url' => 'https://seeli.cam/?utm_source=cbh_youth_online&utm_campaign=seeli_ads&utm_medium=cyo_mobile_home_header',
      'alt_text' => 'SEELI - Chụp. Buông điện thoại xuống. Gặp lại khoảnh khắc ấy sau 30 ngày.',
      'is_active' => true,
      'priority' => 10,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  public function down(): void
  {
    DB::table('cyo_ad_slots')
      ->where('placement', 'mobile-home-header')
      ->where('name', 'SEELI - Header trang chủ mobile')
      ->delete();
  }
};
