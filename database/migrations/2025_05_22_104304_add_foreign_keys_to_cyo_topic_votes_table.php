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
        Schema::table('cyo_topic_votes', function (Blueprint $table) {
            $table->foreign(['topic_id'])->references(['id'])->on('cyo_topics')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('cyo_auth_accounts')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_topic_votes', function (Blueprint $table) {
            $table->dropForeign('cyo_topic_votes_topic_id_foreign');
            $table->dropForeign('cyo_topic_votes_user_id_foreign');
        });
    }
};
