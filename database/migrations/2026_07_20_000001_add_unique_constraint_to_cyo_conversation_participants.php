<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cyo_conversation_participants', function (Blueprint $table) {
            $table->unique(['conversation_id', 'user_id'], 'cyo_conversation_participants_conversation_id_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_conversation_participants', function (Blueprint $table) {
            $table->dropUnique('cyo_conversation_participants_conversation_id_user_id_unique');
        });
    }
};