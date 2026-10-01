<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            // Set only when the author actually changes editable content, as
            // opposed to updated_at, which also moves on moderation/admin updates.
            $table->timestamp('edited_at')->nullable()->after('updated_at');
        });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
