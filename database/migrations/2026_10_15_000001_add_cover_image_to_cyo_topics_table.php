<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Path (public disk) of the cover picture the API drew for a post that has
    // no photo of its own. Separate from cdn_image_id so the post itself does
    // not gain an attachment.
    Schema::table('cyo_topics', function (Blueprint $table) {
      $table->string('cover_image')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_topics', function (Blueprint $table) {
      $table->dropColumn('cover_image');
    });
  }
};
