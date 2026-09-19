<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      // Option groups, e.g. [{"name":"Size","values":["S","M","L"]}]
      $table->json('options')->nullable()->after('image_url');
    });

    Schema::create('cyo_shop_product_variants', function (Blueprint $table) {
      $table->id();
      $table->foreignId('product_id')->constrained('cyo_shop_products')->cascadeOnDelete();
      // Chosen value per option group, e.g. {"Size":"M","Màu":"Đen"}
      $table->json('options');
      $table->string('sku', 64)->nullable()->unique();
      $table->unsignedBigInteger('price');
      $table->integer('stock')->default(0);
      $table->string('image_url')->nullable();
      $table->timestamps();
    });

    Schema::table('cyo_shop_order_items', function (Blueprint $table) {
      $table->foreignId('variant_id')->nullable()->after('product_id')
        ->constrained('cyo_shop_product_variants')->nullOnDelete();
      // Snapshot so the order still reads right if the variant is edited/removed.
      $table->string('variant_label')->nullable()->after('variant_id');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_shop_order_items', function (Blueprint $table) {
      $table->dropConstrainedForeignId('variant_id');
      $table->dropColumn('variant_label');
    });
    Schema::dropIfExists('cyo_shop_product_variants');
    Schema::table('cyo_shop_products', function (Blueprint $table) {
      $table->dropColumn('options');
    });
  }
};
