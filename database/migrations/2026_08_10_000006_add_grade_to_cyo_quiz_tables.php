<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_quiz_questions', function (Blueprint $table) {
      $table->enum('grade', ['10', '11', '12'])->nullable()->after('topic');
    });
    Schema::table('cyo_quiz_sets', function (Blueprint $table) {
      $table->enum('grade', ['10', '11', '12'])->nullable()->after('topic');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_quiz_questions', function (Blueprint $table) {
      $table->dropColumn('grade');
    });
    Schema::table('cyo_quiz_sets', function (Blueprint $table) {
      $table->dropColumn('grade');
    });
  }
};
