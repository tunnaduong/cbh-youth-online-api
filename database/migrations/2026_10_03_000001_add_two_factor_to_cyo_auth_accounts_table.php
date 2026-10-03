<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      // 'totp' (authenticator app) or 'email' (code sent to the account email).
      $table->string('two_factor_method', 10)->nullable();
      // Encrypted base32 TOTP secret - only set for the 'totp' method.
      $table->text('two_factor_secret')->nullable();
      // Encrypted JSON list of sha256 hashes of the unused recovery codes.
      $table->text('two_factor_recovery_codes')->nullable();
      // Null while setup is pending; 2FA is only enforced once this is set.
      $table->timestamp('two_factor_confirmed_at')->nullable();
      // Last accepted TOTP time step, so a code can't be replayed.
      $table->unsignedBigInteger('two_factor_last_step')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropColumn([
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_step',
      ]);
    });
  }
};
