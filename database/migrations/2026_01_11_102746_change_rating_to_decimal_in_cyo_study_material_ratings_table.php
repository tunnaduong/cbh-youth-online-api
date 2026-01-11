<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('cyo_study_material_ratings', function (Blueprint $table) {
      $table->decimal('rating', 3, 2)->default(0)->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_study_material_ratings', function (Blueprint $table) {
      $table->integer('rating')->default(0)->change();
    });
  }
};
