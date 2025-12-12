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
    Schema::create('cyo_user_blocks', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');  // Users who blocked
      $table->unsignedBigInteger('blocked_user_id');  //  Users being blocked
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('blocked_user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');

      $table->unique(['user_id', 'blocked_user_id']);  // Prevent duplicate blocks
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('cyo_user_blocks');
  }
};
