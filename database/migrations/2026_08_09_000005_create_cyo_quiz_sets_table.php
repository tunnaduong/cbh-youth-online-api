<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_quiz_sets', function (Blueprint $table) {
      $table->id();
      // AI picks its own topic each time (never dictated by the user) - kept
      // here mostly so the UI can show what the quiz turned out to be about.
      $table->string('topic');
      $table->enum('difficulty', ['easy', 'medium', 'hard']);
      $table->unsignedInteger('question_count');
      // Full question set including correct answers/explanations - never
      // sent to clients directly, only read server-side (see QuizController).
      $table->json('questions');
      // How many distinct users have been served this exact set. Once a set
      // hits QuizGenerationService::MAX_SERVES, it's retired from the
      // reuse pool and a fresh one gets generated instead.
      $table->unsignedInteger('served_count')->default(0);
      $table->timestamps();

      $table->index(['difficulty', 'question_count', 'served_count']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_quiz_sets');
  }
};
