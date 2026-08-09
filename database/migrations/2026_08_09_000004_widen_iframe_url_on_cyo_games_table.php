<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('cyo_games', function (Blueprint $table) {
      // Famobi's embed URLs carry long tracking query strings that overflow
      // the default varchar(255).
      $table->text('iframe_url')->change();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_games', function (Blueprint $table) {
      $table->string('iframe_url')->change();
    });
  }
};
