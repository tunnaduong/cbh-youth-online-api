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
    Schema::create('cyo_user_point_deductions', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->integer('points_deducted')->default(0);
      $table->string('reason');
      $table->text('description')->nullable();
      $table->unsignedBigInteger('admin_id'); // Admin who applied the deduction
      $table->boolean('is_active')->default(true); // Can be reversed
      $table->timestamp('expires_at')->nullable(); // Optional expiration
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('admin_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->index(['user_id', 'is_active']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_user_point_deductions');
  }
};
