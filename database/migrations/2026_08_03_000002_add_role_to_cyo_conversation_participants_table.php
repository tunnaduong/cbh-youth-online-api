<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversation_participants', function (Blueprint $table) {
            $table->string('role')->default('member')->after('user_id'); // owner or member
        });

        // Backfill: the creator of each existing group conversation (earliest participant
        // to join) becomes the owner so existing groups have a valid owner.
        $groupConversationIds = DB::table('cyo_conversations')
            ->where('type', 'group')
            ->where('is_public', false)
            ->pluck('id');

        foreach ($groupConversationIds as $conversationId) {
            $firstParticipant = DB::table('cyo_conversation_participants')
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($firstParticipant) {
                DB::table('cyo_conversation_participants')
                    ->where('id', $firstParticipant->id)
                    ->update(['role' => 'owner']);

                DB::table('cyo_conversations')
                    ->where('id', $conversationId)
                    ->whereNull('created_by')
                    ->update(['created_by' => $firstParticipant->user_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cyo_conversation_participants', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
