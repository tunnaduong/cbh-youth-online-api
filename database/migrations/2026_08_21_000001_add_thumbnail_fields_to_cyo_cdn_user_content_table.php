<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_cdn_user_content', function (Blueprint $table) {
      // Small (480px) generated copies for feed/preview rendering - see
      // MediaThumbnailService. thumbnail_path applies to both images (a
      // downscaled copy) and videos (a first-frame JPG, same as chat's);
      // preview_path is video-only (a small muted low-bitrate clip for
      // inline/feed autoplay). Both nullable/lazily filled in - older rows
      // and any that failed to generate one just fall back to file_path.
      $table->string('thumbnail_path')->nullable()->after('file_path');
      $table->string('preview_path')->nullable()->after('thumbnail_path');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_cdn_user_content', function (Blueprint $table) {
      $table->dropColumn(['thumbnail_path', 'preview_path']);
    });
  }
};
