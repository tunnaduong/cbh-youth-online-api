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
    Schema::table('cyo_forum_main_categories', function (Blueprint $table) {
      $table->text('seo_description')->nullable()->after('description');
    });

    Schema::table('cyo_forum_subforums', function (Blueprint $table) {
      $table->text('seo_description')->nullable()->after('description');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_forum_main_categories', function (Blueprint $table) {
      $table->dropColumn('seo_description');
    });

    Schema::table('cyo_forum_subforums', function (Blueprint $table) {
      $table->dropColumn('seo_description');
    });
  }
};
