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
        Schema::table('cyo_notification_settings', function (Blueprint $table) {
            $table->foreign(['user_id'], 'cyo_notification_settings_ibfk_1')->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_notification_settings', function (Blueprint $table) {
            $table->dropForeign('cyo_notification_settings_ibfk_1');
        });
    }
};
