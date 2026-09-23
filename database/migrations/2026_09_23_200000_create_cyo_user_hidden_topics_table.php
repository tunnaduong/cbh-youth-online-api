<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cyo_user_hidden_topics', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('topic_id');
      $table->timestamps();

      // One row per (user, topic) - hiding an already hidden post is a no-op.
      $table->unique(['user_id', 'topic_id']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('topic_id')->references('id')->on('cyo_topics')->onDelete('cascade');
      $table->index('topic_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_user_hidden_topics');
  }
};
