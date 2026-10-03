<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Two-factor methods can now be on side by side, so each one records
    // when it was confirmed. `two_factor_method` becomes the default method
    // offered at login and `two_factor_confirmed_at` stays as "any method
    // is on" (the legacy session login only looks at that column).
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->timestamp('two_factor_totp_confirmed_at')->nullable();
      $table->timestamp('two_factor_email_confirmed_at')->nullable();
    });

    // Accounts that turned two-factor on while only one method was allowed.
    DB::table('cyo_auth_accounts')
      ->whereNotNull('two_factor_confirmed_at')
      ->where('two_factor_method', 'totp')
      ->update(['two_factor_totp_confirmed_at' => DB::raw('two_factor_confirmed_at')]);

    DB::table('cyo_auth_accounts')
      ->whereNotNull('two_factor_confirmed_at')
      ->where('two_factor_method', 'email')
      ->update(['two_factor_email_confirmed_at' => DB::raw('two_factor_confirmed_at')]);
  }

  public function down(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropColumn(['two_factor_totp_confirmed_at', 'two_factor_email_confirmed_at']);
    });
  }
};
