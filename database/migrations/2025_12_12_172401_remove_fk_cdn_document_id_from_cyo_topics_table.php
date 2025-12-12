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
    Schema::table('cyo_topics', function (Blueprint $table) {
      // Drop the foreign key
      $table->dropForeign('cyo_topics_cdn_document_id_foreign');

      // Drop the index as well.
      $table->dropIndex('cyo_topics_cdn_document_id_foreign');
    });

    Schema::table('cyo_topics', function (Blueprint $table) {
      // Change the column to text to support multiple IDs (e.g. "362,363,364")
      $table->text('cdn_document_id')->nullable()->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      // Revert back to unsignedBigInteger if possible, but this might fail if there's non-integer data.
      // We'll try to revert the structure, but data loss might occur if we force it.
      // Ideally we should clean up data, but for down() we just define structure.
      $table->unsignedBigInteger('cdn_document_id')->nullable()->change();

      $table
        ->foreign('cdn_document_id', 'cyo_topics_cdn_document_id_foreign')
        ->references('id')
        ->on('cyo_cdn_user_content')
        ->onUpdate('cascade')
        ->onDelete('set null');
    });
  }
};
