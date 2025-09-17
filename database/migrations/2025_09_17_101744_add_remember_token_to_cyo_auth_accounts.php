<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->rememberToken()->after('provider');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_auth_accounts', function (Blueprint $table) {
      $table->dropColumn('remember_token');
    });
  }
};
