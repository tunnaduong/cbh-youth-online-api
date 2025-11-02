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
    Schema::table('cyo_notification_subscriptions', function (Blueprint $table) {
      // Add composite unique constraint for (user_id, endpoint)
      // This ensures one user can only have one subscription per endpoint
      // Prevents duplicate subscriptions from race conditions
      $table->unique(['user_id', 'endpoint'], 'notification_subscriptions_user_endpoint_unique');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_notification_subscriptions', function (Blueprint $table) {
      // Drop the composite unique constraint
      $table->dropUnique('notification_subscriptions_user_endpoint_unique');
    });
  }
};
