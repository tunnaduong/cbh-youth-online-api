<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Which login (personal_access_tokens.id) made the browser's web push
    // subscription, so ending that login also ends its pushes - the same as
    // cyo_expo_push_tokens.access_token_id for the mobile app. Null for
    // subscriptions made before this column existed.
    Schema::table('cyo_notification_subscriptions', function (Blueprint $table) {
      $table->unsignedBigInteger('access_token_id')->nullable()->index();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_notification_subscriptions', function (Blueprint $table) {
      $table->dropColumn('access_token_id');
    });
  }
};
