<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * iOS PushKit (VoIP) tokens: the only push that lets iOS ring a call through
   * CallKit while the app is in the background or killed. Expo push cannot
   * send these, so they are kept apart from cyo_expo_push_tokens and sent
   * straight to APNs (App\Services\ApnsVoipService).
   */
  public function up(): void
  {
    Schema::create('cyo_voip_push_tokens', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
      $table->string('voip_token', 200)->unique();
      // 'production' or 'sandbox'; learned from APNs on the first send.
      $table->string('apns_environment', 20)->nullable();
      $table->unsignedBigInteger('access_token_id')->nullable();
      $table->boolean('is_active')->default(true);
      $table->timestamp('last_used_at')->nullable();
      $table->timestamps();

      $table->index(['user_id', 'is_active']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_voip_push_tokens');
  }
};
