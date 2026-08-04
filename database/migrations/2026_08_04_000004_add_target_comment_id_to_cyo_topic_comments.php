<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            // Tracks the actual comment the user was replying to before nesting was capped.
            // When replying to a level-3 comment (capped to level-2 in replying_to),
            // this column stores the original level-3 target so the UI can sandwich
            // the new reply right after that comment.
            $table->unsignedBigInteger('target_comment_id')->nullable()->after('replying_to');
            $table->foreign('target_comment_id')->references('id')->on('cyo_topic_comments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropForeign(['target_comment_id']);
            $table->dropColumn('target_comment_id');
        });
    }
};
