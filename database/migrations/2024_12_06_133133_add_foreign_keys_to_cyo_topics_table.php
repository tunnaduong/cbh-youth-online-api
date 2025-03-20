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
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->foreign(['cdn_image_id'])->references(['id'])->on('cyo_cdn_user_content')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['subforum_id'])->references(['id'])->on('cyo_forum_subforums')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropForeign('cyo_topics_cdn_image_id_foreign');
            $table->dropForeign('cyo_topics_subforum_id_foreign');
            $table->dropForeign('cyo_topics_user_id_foreign');
        });
    }
};
