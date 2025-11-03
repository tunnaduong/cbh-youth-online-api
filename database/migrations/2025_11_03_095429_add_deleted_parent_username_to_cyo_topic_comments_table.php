<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      $table->string('deleted_parent_username')->nullable()->after('replying_to');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      $table->dropColumn('deleted_parent_username');
    });
  }
};
