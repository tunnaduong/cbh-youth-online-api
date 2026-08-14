<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_quiz_sets', function (Blueprint $table) {
      // Null for the existing AI-generated/random quiz flow. Set for a
      // creator-uploaded custom quiz - see CustomQuizController. Used to
      // exempt the creator from earning points/XP off their own quiz.
      $table->foreignId('creator_id')->nullable()->after('id')
        ->constrained('cyo_auth_accounts')->nullOnDelete();
      $table->boolean('is_custom')->default(false)->after('creator_id');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_quiz_sets', function (Blueprint $table) {
      $table->dropConstrainedForeignId('creator_id');
      $table->dropColumn('is_custom');
    });
  }
};
