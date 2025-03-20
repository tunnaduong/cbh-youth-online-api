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
        Schema::create('cyo_topic_comment_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('comment_id')->index('cyo_topic_comment_votes_comment_id_foreign');
            $table->unsignedBigInteger('user_id')->index('cyo_topic_comment_votes_user_id_foreign');
            $table->tinyInteger('vote_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_topic_comment_votes');
    }
};
