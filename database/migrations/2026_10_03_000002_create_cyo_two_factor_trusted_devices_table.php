<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cyo_two_factor_trusted_devices', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      // sha256 of the device token the client keeps - the raw token is never stored.
      $table->string('token_hash', 64);
      $table->string('device_name', 255)->nullable();
      $table->timestamp('last_used_at')->nullable();
      $table->timestamp('expires_at');
      $table->timestamps();

      // One device keeps a single token for every account signed in on it
      // (account switcher), so the token is only unique per account.
      $table->unique(['user_id', 'token_hash']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_two_factor_trusted_devices');
  }
};
