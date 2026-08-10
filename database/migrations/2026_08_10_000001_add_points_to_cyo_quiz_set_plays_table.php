<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_quiz_set_plays', function (Blueprint $table) {
      $table->unsignedInteger('points')->default(0)->after('score');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_quiz_set_plays', function (Blueprint $table) {
      $table->dropColumn('points');
    });
  }
};
