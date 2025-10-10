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
      // Add index for better performance on cleanup queries
      $table->index(['ip_address', 'user_agent', 'last_activity'], 'cleanup_index');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_online_users', function (Blueprint $table) {
      // Drop the index
      $table->dropIndex('cleanup_index');
    });
  }
};
