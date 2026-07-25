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
        Schema::create('cyo_hashtags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tag')->unique();
            $table->timestamps();
        });

        Schema::create('cyo_topic_hashtags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('topic_id')->index('cyo_topic_hashtags_topic_id_foreign');
            $table->unsignedBigInteger('hashtag_id')->index('cyo_topic_hashtags_hashtag_id_foreign');
            $table->timestamps();

            $table->unique(['topic_id', 'hashtag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_topic_hashtags');
        Schema::dropIfExists('cyo_hashtags');
    }
};
