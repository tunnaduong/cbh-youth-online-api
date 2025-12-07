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
    Schema::create('cyo_study_material_purchases', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('study_material_id');
      $table->integer('price_paid')->default(0); // points paid at time of purchase
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('study_material_id')->references('id')->on('cyo_study_materials')->onDelete('cascade');
      $table->unique(['user_id', 'study_material_id']); // User can only purchase once
      $table->index(['user_id']);
      $table->index(['study_material_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_study_material_purchases');
  }
};


