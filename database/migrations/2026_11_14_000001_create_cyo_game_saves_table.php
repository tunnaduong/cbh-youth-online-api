<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // One row per (account, game): the cookies and localStorage the game
    // proxy captured, so the same account continues on another device.
    Schema::create('cyo_game_saves', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('game_id');
      // JSON text: [{"name": "...", "value": "..."}]
      $table->longText('cookies')->nullable();
      // JSON text: {"key": "value"} (localStorage)
      $table->longText('storage')->nullable();
      $table->unsignedInteger('size_bytes')->default(0);
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('game_id')->references('id')->on('cyo_games')->onDelete('cascade');
      $table->unique(['user_id', 'game_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_game_saves');
  }
};
