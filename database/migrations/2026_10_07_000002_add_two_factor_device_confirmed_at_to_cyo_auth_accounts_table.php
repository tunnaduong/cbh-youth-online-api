<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Third two-factor method, next to the authenticator app and the email
    // code: approving the login on a device that is already logged in.
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->timestamp('two_factor_device_confirmed_at')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropColumn('two_factor_device_confirmed_at');
    });
  }
};
