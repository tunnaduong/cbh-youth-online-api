<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversation_messages', function (Blueprint $table) {
            $table->boolean('is_forwarded')->default(false)->after('reply_to_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_conversation_messages', function (Blueprint $table) {
            $table->dropColumn('is_forwarded');
        });
    }
};
