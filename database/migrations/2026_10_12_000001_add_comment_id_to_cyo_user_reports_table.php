<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Lets a report point at one comment, so comments can be reported like
   * posts, stories and chat messages.
   */
  public function up()
  {
    Schema::table('cyo_user_reports', function (Blueprint $table) {
      $table->unsignedBigInteger('comment_id')->nullable()->after('topic_id');
      $table->index('comment_id');
    });
  }

  public function down()
  {
    Schema::table('cyo_user_reports', function (Blueprint $table) {
      $table->dropIndex(['comment_id']);
      $table->dropColumn('comment_id');
    });
  }
};
