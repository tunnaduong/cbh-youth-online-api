<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_quiz_set_plays', function (Blueprint $table) {
      // Set once, the first time a non-creator play of a given quiz set
      // earns points, and never cleared again - even across a restart that
      // resets score/answers/submitted_at for another attempt. Lets a user
      // replay the same quiz set as many times as they like (see
      // QuizController::restart) while only ever being paid out once for it.
      $table->timestamp('points_awarded_at')->nullable()->after('points');
    });

    // Backfill: any already-completed play that actually earned points was,
    // by definition, that user's first (and only, pre-restart) completion.
    DB::table('cyo_quiz_set_plays')
      ->whereNotNull('submitted_at')
      ->where('points', '>', 0)
      ->update(['points_awarded_at' => DB::raw('submitted_at')]);
  }

  public function down(): void
  {
    Schema::table('cyo_quiz_set_plays', function (Blueprint $table) {
      $table->dropColumn('points_awarded_at');
    });
  }
};
