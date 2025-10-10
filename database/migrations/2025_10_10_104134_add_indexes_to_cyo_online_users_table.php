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
      // Index for user_id lookups (authenticated users)
      $table->index('user_id');

      // Index for IP + User-Agent lookups (guests)
      $table->index(['ip_address', 'user_agent']);

      // Index for cleanup queries
      $table->index('last_activity');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_online_users', function (Blueprint $table) {
      $table->dropIndex(['user_id']);
      $table->dropIndex(['ip_address', 'user_agent']);
      $table->dropIndex(['last_activity']);
    });
  }
};
