<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cyo_game_player_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('game_id');
            // Encrypted JSON: cookies the game's server set for this player.
            $table->longText('cookies');
            // Encrypted JSON: the game's localStorage for this player (key => value).
            $table->longText('storage');
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            $table->foreign('game_id')->references('id')->on('cyo_games')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cyo_game_player_states');
    }
};
