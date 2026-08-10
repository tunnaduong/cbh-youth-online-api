<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_quiz_questions', function (Blueprint $table) {
      $table->id();
      $table->string('topic');
      $table->enum('difficulty', ['easy', 'medium', 'hard']);
      $table->text('question');
      $table->json('options');
      $table->char('answer', 1);
      $table->text('explanation')->nullable();
      $table->timestamps();

      $table->index('difficulty');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_quiz_questions');
  }
};
