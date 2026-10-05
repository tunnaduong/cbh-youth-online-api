<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // How each login was made, shown in "logged-in devices": password,
    // google, facebook, apple, passkey, register (signed up on that device)
    // or app (session handed to a browser by the mobile app). Null for
    // logins from before this column existed.
    Schema::table('personal_access_tokens', function (Blueprint $table) {
      $table->string('login_method', 20)->nullable();
      $table->boolean('login_two_factor')->default(false);
    });
  }

  public function down(): void
  {
    Schema::table('personal_access_tokens', function (Blueprint $table) {
      $table->dropColumn(['login_method', 'login_two_factor']);
    });
  }
};
