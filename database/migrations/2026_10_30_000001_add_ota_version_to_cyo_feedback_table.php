<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_feedback', function (Blueprint $table) {
      $table->string('ota_version', 50)->nullable()->after('app_version');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_feedback', function (Blueprint $table) {
      $table->dropColumn('ota_version');
    });
  }
};
