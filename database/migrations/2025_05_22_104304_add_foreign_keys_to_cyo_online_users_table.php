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
        Schema::table('cyo_online_users', function (Blueprint $table) {
            $table->foreign(['user_id'], 'ibfk_user_id_online')->references(['id'])->on('cyo_auth_accounts')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_online_users', function (Blueprint $table) {
            $table->dropForeign('ibfk_user_id_online');
        });
    }
};
