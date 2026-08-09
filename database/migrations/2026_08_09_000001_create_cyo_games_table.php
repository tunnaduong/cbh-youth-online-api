<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_games', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->string('description')->nullable();
      $table->string('category'); // arcade, puzzle, racing, adventure, shooting, other
      $table->string('image_url');
      $table->string('iframe_url');
      // Which device(s) this game is actually playable on - controls/UI may
      // not work on the excluded platform (e.g. a PC game needing a keyboard).
      $table->enum('platform', ['pc', 'mobile', 'both'])->default('both');
      $table->boolean('is_active')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();

      $table->index(['is_active', 'category']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_games');
  }
};
