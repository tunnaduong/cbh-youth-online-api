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
        Schema::create('cyo_topic_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replying_to')->nullable()->index('fk_replying_to');
            $table->unsignedBigInteger('topic_id')->index('cyo_topic_comments_topic_id_foreign');
            $table->unsignedBigInteger('user_id')->index('cyo_topic_comments_user_id_foreign');
            $table->text('comment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_topic_comments');
    }
};
