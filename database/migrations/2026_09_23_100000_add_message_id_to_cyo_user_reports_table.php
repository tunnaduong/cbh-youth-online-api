<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Lets a report point at one specific chat message instead of only at a
   * post, a story or the user as a whole.
   */
  public function up()
  {
    Schema::table('cyo_user_reports', function (Blueprint $table) {
      $table->unsignedBigInteger('message_id')->nullable()->after('story_id');
      $table->index('message_id');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('cyo_user_reports', function (Blueprint $table) {
      $table->dropIndex(['message_id']);
      $table->dropColumn('message_id');
    });
  }
};
