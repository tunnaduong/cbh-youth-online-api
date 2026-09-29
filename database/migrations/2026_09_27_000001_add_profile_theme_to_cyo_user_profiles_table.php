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
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            // Profile appearance customization (theme colors, name style,
            // avatar frame) - see App\Services\ProfileThemeService for the
            // shape and which tier privilege unlocks each option.
            $table->json('profile_theme')->nullable()->after('cover_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->dropColumn('profile_theme');
        });
    }
};
