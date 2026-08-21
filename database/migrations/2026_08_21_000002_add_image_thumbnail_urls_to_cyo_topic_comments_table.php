<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      // Index-paired with image_urls (see MediaThumbnailService/
      // TopicsController::addComment) - small 480px copies for the small
      // inline preview a comment shows, rather than decoding the full
      // original for a ~200px thumbnail. Nullable/JSON like image_urls;
      // older comments and any generation failure just fall back to it.
      $table->json('image_thumbnail_urls')->nullable()->after('image_urls');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_topic_comments', function (Blueprint $table) {
      $table->dropColumn('image_thumbnail_urls');
    });
  }
};
