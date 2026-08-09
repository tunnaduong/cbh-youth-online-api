<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_quiz_set_plays', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('quiz_set_id');
      $table->unsignedBigInteger('user_id');
      // Filled in once the user submits answers - null means they were
      // handed the set but never finished it (still counts as "used" for
      // them, so they never see the exact same set twice).
      $table->unsignedInteger('score')->nullable();
      $table->timestamp('submitted_at')->nullable();
      $table->timestamps();

      $table->foreign('quiz_set_id')->references('id')->on('cyo_quiz_sets')->cascadeOnDelete();
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->cascadeOnDelete();
      // A user can never be served (or replay) the same generated set twice.
      $table->unique(['quiz_set_id', 'user_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_quiz_set_plays');
  }
};
