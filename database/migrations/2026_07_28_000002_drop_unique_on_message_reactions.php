<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_message_reactions', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['message_id', 'user_id', 'reaction_type']);
            $table->foreign('message_id')->references('id')->on('cyo_conversation_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_message_reactions', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropForeign(['user_id']);
            $table->unique(['message_id', 'user_id', 'reaction_type']);
            $table->foreign('message_id')->references('id')->on('cyo_conversation_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });
    }
};
