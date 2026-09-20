<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cyo_daily_checkins', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->date('checkin_date');
      $table->integer('points_awarded');
      $table->integer('streak_day'); // ngày streak liên tiếp (1, 2, 3, ...)
      $table->timestamps();

      $table->unique(['user_id', 'checkin_date']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->index(['user_id', 'checkin_date']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_daily_checkins');
  }
};
