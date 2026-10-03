<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // What the user sees in "logged-in devices": each token is one login on
    // one device, described by the X-Client-*/X-Device-* headers it sends.
    Schema::table('personal_access_tokens', function (Blueprint $table) {
      $table->string('platform', 20)->nullable();
      $table->string('app_version', 50)->nullable();
      $table->string('device_name', 255)->nullable();
      $table->string('device_model', 100)->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('personal_access_tokens', function (Blueprint $table) {
      $table->dropColumn(['platform', 'app_version', 'device_name', 'device_model']);
    });
  }
};
