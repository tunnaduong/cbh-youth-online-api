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
    Schema::table('cyo_online_users', function (Blueprint $table) {
      $table->string('user_agent', 255)->nullable()->after('ip_address');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_online_users', function (Blueprint $table) {
      $table->dropColumn('user_agent');
    });
  }
};
