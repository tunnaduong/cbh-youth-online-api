<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_quiz_question_seen', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('cyo_auth_accounts')->cascadeOnDelete();
      $table->foreignId('quiz_question_id')->constrained('cyo_quiz_questions')->cascadeOnDelete();
      $table->timestamp('created_at')->nullable();

      $table->unique(['user_id', 'quiz_question_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_quiz_question_seen');
  }
};
