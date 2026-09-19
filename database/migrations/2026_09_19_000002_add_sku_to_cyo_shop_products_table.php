<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      $table->string('sku', 64)->nullable()->unique()->after('slug');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      $table->dropUnique(['sku']);
      $table->dropColumn('sku');
    });
  }
};
