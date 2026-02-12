<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('cyo_shop_categories', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->timestamps();
    });

    Schema::create('cyo_shop_products', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->unsignedBigInteger('price');
      $table->integer('stock')->default(0);
      $table->string('image_url')->nullable();
      $table->foreignId('category_id')->constrained('cyo_shop_categories');
      $table->boolean('is_active')->default(true);
      $table->timestamps();
      $table->softDeletes();
    });

    Schema::create('cyo_shop_orders', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('cyo_auth_accounts');
      $table->unsignedBigInteger('total_amount');
      $table->string('status')->default('pending');  // pending, processing, shipped, completed, cancelled
      $table->string('shipping_address');
      $table->string('phone');
      $table->text('note')->nullable();
      $table->timestamps();
    });

    Schema::create('cyo_shop_order_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('order_id')->constrained('cyo_shop_orders')->cascadeOnDelete();
      $table->foreignId('product_id')->constrained('cyo_shop_products');
      $table->integer('quantity');
      $table->unsignedBigInteger('price');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_shop_order_items');
    Schema::dropIfExists('cyo_shop_orders');
    Schema::dropIfExists('cyo_shop_products');
    Schema::dropIfExists('cyo_shop_categories');
  }
};
