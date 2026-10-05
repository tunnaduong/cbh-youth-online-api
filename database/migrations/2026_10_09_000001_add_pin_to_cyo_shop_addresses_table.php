<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // The spot the customer confirmed on the map for a saved address, so the
    // next order to it starts with the pin already in place.
    Schema::table('cyo_shop_addresses', function (Blueprint $table) {
      $table->decimal('lat', 10, 7)->nullable()->after('province');
      $table->decimal('lng', 10, 7)->nullable()->after('lat');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_shop_addresses', function (Blueprint $table) {
      $table->dropColumn(['lat', 'lng']);
    });
  }
};
