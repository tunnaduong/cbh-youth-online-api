<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      $table->json('image_urls')->nullable()->after('image_url');
    });

    // Populate image_urls from existing image_url if present
    DB::table('cyo_shop_products')
      ->whereNotNull('image_url')
      ->where('image_url', '!=', '')
      ->whereNull('image_urls')
      ->get(['id', 'image_url'])
      ->each(function ($p) {
        DB::table('cyo_shop_products')
          ->where('id', $p->id)
          ->update(['image_urls' => json_encode([$p->image_url])]);
      });
  }

  public function down(): void
  {
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      $table->dropColumn('image_urls');
    });
  }
};
