<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // The user's own choice: whether a login through Google/Facebook/Apple
    // skips the two-factor step. On by default - the provider has already
    // signed the person in - and switchable in the two-factor settings.
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->boolean('two_factor_skip_social')->default(true);
    });
  }

  public function down(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropColumn('two_factor_skip_social');
    });
  }
};
