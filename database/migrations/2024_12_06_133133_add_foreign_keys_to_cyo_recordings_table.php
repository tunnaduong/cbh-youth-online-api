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
        Schema::table('cyo_recordings', function (Blueprint $table) {
            $table->foreign(['cdn_audio_id'], 'fk_cdn_audio_id')->references(['id'])->on('cyo_cdn_user_content')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['cdn_preview_id'], 'fk_cdn_preview_id')->references(['id'])->on('cyo_cdn_user_content')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['user_id'], 'fk_user_id')->references(['id'])->on('cyo_auth_accounts')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_recordings', function (Blueprint $table) {
            $table->dropForeign('fk_cdn_audio_id');
            $table->dropForeign('fk_cdn_preview_id');
            $table->dropForeign('fk_user_id');
        });
    }
};
