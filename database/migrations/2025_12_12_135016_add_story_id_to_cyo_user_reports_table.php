<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up()
  {
    Schema::table('cyo_user_reports', function (Blueprint $table) {
      $table->unsignedBigInteger('story_id')->nullable()->after('topic_id');
      // Assuming stories table is 'cyo_stories' or similar.
      // Wait, looking at StoryController, the model is Story.
      // I need to check the table name for Story. It's usually 'stories' or 'cyo_stories'.
      // I'll check Story model next, but for now I'll just add the column.
      // I won't add foreign key lightly without knowing table name, but I can guess or check.
      // Let's just add the column first.
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
      $table->dropColumn('story_id');
    });
  }
};
