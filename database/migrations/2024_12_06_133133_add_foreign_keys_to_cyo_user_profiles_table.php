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
            $table->foreign(['auth_account_id'])->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['profile_picture'])->references(['id'])->on('cyo_cdn_user_content')->onUpdate('restrict')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->dropForeign('cyo_user_profiles_auth_account_id_foreign');
            $table->dropForeign('cyo_user_profiles_profile_picture_foreign');
        });
    }
};
