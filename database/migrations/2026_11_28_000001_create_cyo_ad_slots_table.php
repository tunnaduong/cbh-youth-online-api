<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_ad_slots', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('placement', 50)->index(); // sidebar | post-bottom | feed-inline
      $table->string('image_url', 500);
      $table->string('link_url', 500)->nullable();
      $table->string('alt_text')->nullable();
      $table->boolean('is_active')->default(true);
      $table->integer('priority')->default(0);
      $table->timestamp('starts_at')->nullable();
      $table->timestamp('ends_at')->nullable();
      $table->unsignedBigInteger('impressions')->default(0);
      $table->unsignedBigInteger('clicks')->default(0);
      $table->timestamps();
    });

    DB::table('cyo_ad_slots')->insert([
      'name' => 'SEELI - See more. Live more.',
      'placement' => 'sidebar',
      'image_url' => 'https://chuyenbienhoa.com/images/ads/seeli.jpg',
      'link_url' => 'https://seeli.cam',
      'alt_text' => 'SEELI - Chụp. Buông điện thoại xuống. Gặp lại khoảnh khắc ấy sau 30 ngày.',
      'is_active' => true,
      'priority' => 10,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_ad_slots');
  }
};
