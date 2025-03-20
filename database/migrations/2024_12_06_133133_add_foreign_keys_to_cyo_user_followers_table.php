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
        Schema::table('cyo_user_followers', function (Blueprint $table) {
            $table->foreign(['follower_id'], 'cyo_user_followers_ibfk_1')->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['followed_id'], 'cyo_user_followers_ibfk_2')->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_user_followers', function (Blueprint $table) {
            $table->dropForeign('cyo_user_followers_ibfk_1');
            $table->dropForeign('cyo_user_followers_ibfk_2');
        });
    }
};
