<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Shop support threads: while on, the AI answers the customer's messages;
    // the customer turns it off to wait for a real person.
    Schema::table('cyo_conversations', function (Blueprint $table) {
      $table->boolean('shop_ai_enabled')->default(true)->after('is_shop_support');
    });

    // The gift shop cart, kept per account so it follows the user across
    // devices (it used to live only in the browser's localStorage).
    Schema::create('cyo_shop_cart_items', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('product_id');
      // 0 = the product itself (no variant); kept non-null so the unique
      // index below treats "no variant" as one line.
      $table->unsignedBigInteger('variant_id')->default(0);
      $table->unsignedInteger('quantity');
      $table->timestamps();

      $table->unique(['user_id', 'product_id', 'variant_id']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('product_id')->references('id')->on('cyo_shop_products')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_shop_cart_items');

    Schema::table('cyo_conversations', function (Blueprint $table) {
      $table->dropColumn('shop_ai_enabled');
    });
  }
};
