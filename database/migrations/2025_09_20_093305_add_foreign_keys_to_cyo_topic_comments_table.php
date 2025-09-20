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
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->foreign(['topic_id'])->references(['id'])->on('cyo_topics')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropForeign('cyo_topic_comments_topic_id_foreign');
        });
    }
};
