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
        Schema::table('cyo_conversation_messages', function (Blueprint $table) {
            $table->foreign(['conversation_id'])
                ->references(['id'])
                ->on('cyo_conversations')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            $table->foreign(['user_id'])
                ->references(['id'])
                ->on('cyo_auth_accounts')
                ->onUpdate('restrict')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_conversation_messages', function (Blueprint $table) {
            $table->dropForeign('cyo_conversation_messages_conversation_id_foreign');
            $table->dropForeign('cyo_conversation_messages_user_id_foreign');
        });
    }
};