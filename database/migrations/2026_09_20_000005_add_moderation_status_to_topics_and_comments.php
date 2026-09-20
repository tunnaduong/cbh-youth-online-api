<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            // 'approved' = visible, 'pending' = awaiting human review, 'rejected' = auto-rejected
            $table->enum('moderation_status', ['approved', 'pending', 'rejected'])->default('approved')->after('privacy');
        });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->enum('moderation_status', ['approved', 'pending', 'rejected'])->default('approved')->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropColumn('moderation_status');
        });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropColumn('moderation_status');
        });
    }
};
