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
        Schema::table('cyo_recording_views', function (Blueprint $table) {
            $table->foreign(['record_id'], 'fk_record_id_recording')->references(['id'])->on('cyo_recordings')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['user_id'], 'fk_user_id_record_view')->references(['id'])->on('cyo_auth_accounts')->onUpdate('set null')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_recording_views', function (Blueprint $table) {
            $table->dropForeign('fk_record_id_recording');
            $table->dropForeign('fk_user_id_record_view');
        });
    }
};
