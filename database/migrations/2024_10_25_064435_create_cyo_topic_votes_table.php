<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cyo_topic_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('vote_value'); // Assuming 1 for upvote, -1 for downvote
            $table->timestamps();

            $table->foreign('topic_id')->references('id')->on('cyo_topics')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cyo_topic_votes');
    }
};
