<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Devices an account has logged in from before. Outlives the login
    // itself (tokens are deleted on logout), so logging back in on the same
    // device doesn't count as a new device again.
    Schema::create('cyo_known_devices', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      // sha256 of platform + device name + model, see DeviceSessionService.
      $table->string('fingerprint', 64);
      $table->string('platform', 20)->nullable();
      $table->string('device_name', 255)->nullable();
      $table->string('device_model', 100)->nullable();
      $table->timestamp('last_login_at')->nullable();
      $table->timestamps();

      $table->unique(['user_id', 'fingerprint']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_known_devices');
  }
};
