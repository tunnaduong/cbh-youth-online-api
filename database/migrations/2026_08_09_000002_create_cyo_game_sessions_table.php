<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_game_sessions', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('game_id');
      $table->timestamp('started_at');
      $table->timestamp('ended_at')->nullable();
      $table->unsignedInteger('duration_seconds')->default(0);
      $table->unsignedInteger('xp_earned')->default(0);
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('game_id')->references('id')->on('cyo_games')->onDelete('cascade');
      $table->index(['game_id', 'started_at']);
      $table->index(['user_id', 'game_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_game_sessions');
  }
};
