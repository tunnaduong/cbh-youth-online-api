<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      $table->boolean('hidden_from_latest')->default(false)->after('hidden');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      $table->dropColumn('hidden_from_latest');
    });
  }
};
