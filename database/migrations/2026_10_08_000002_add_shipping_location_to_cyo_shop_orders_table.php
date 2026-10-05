<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Where the customer dropped the pin on the map at checkout, next to the
    // address they typed - so whoever delivers can open the exact spot.
    Schema::table('cyo_shop_orders', function (Blueprint $table) {
      $table->decimal('shipping_lat', 10, 7)->nullable()->after('shipping_address');
      $table->decimal('shipping_lng', 10, 7)->nullable()->after('shipping_lat');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_shop_orders', function (Blueprint $table) {
      $table->dropColumn(['shipping_lat', 'shipping_lng']);
    });
  }
};
