<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Stories go through the same moderation as posts and comments: a story
    // held for a human ("pending") is only shown to its author until an
    // admin approves it. Existing stories stay as they are (approved).
    Schema::table('cyo_stories', function (Blueprint $table) {
      $table->enum('moderation_status', ['approved', 'pending', 'rejected'])->default('approved')->index();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_stories', function (Blueprint $table) {
      $table->dropColumn('moderation_status');
    });
  }
};
