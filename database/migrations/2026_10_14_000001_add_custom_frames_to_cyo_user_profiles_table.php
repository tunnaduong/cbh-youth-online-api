<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // The image a Pro Plus member uploaded as the frame around their avatar /
    // their profile: its path on the public disk. Kept out of the
    // profile_theme JSON, which clients write, so a frame can only ever be a
    // file the API stored itself.
    Schema::table('cyo_user_profiles', function (Blueprint $table) {
      $table->string('custom_avatar_frame')->nullable()->after('profile_theme');
      $table->string('custom_profile_frame')->nullable()->after('custom_avatar_frame');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_user_profiles', function (Blueprint $table) {
      $table->dropColumn(['custom_avatar_frame', 'custom_profile_frame']);
    });
  }
};
