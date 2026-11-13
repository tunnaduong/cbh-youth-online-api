<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * One class timetable per account, shared by the web and the app. `data` is
   * the vaolop.app format: { "T2": [{type, start, end, subject|label}], ... }.
   */
  public function up(): void
  {
    Schema::create('cyo_timetables', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->unique()->constrained('cyo_auth_accounts')->onDelete('cascade');
      $table->json('data');
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_timetables');
  }
};
