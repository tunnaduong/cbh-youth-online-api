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
    Schema::create('cyo_pending_deposits', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->string('deposit_code')->unique(); // Code for user to include in transfer
      $table->integer('amount_vnd'); // Expected amount in VND
      $table->integer('expected_points'); // Expected points after fee
      $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
      $table->timestamp('expires_at'); // Code expires after 24 hours
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->index(['deposit_code', 'status']);
      $table->index(['user_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_pending_deposits');
  }
};


