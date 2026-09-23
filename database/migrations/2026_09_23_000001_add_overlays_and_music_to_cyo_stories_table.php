<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('cyo_stories', function (Blueprint $table) {
      // Editor overlays (text, stickers, mentions, links, music chip...) with
      // normalized 9:16 coordinates so every client can lay them out itself.
      $table->json('overlays')->nullable()->after('text_position');
      // Selected soundtrack: provider, track metadata and the trimmed range.
      $table->json('music')->nullable()->after('overlays');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_stories', function (Blueprint $table) {
      $table->dropColumn(['overlays', 'music']);
    });
  }
};
