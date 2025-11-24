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
    Schema::create('cyo_withdrawal_requests', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->integer('amount'); // points
      $table->string('bank_account');
      $table->string('bank_name');
      $table->string('account_holder');
      $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
      $table->unsignedBigInteger('admin_id')->nullable();
      $table->text('admin_note')->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('admin_id')->references('id')->on('cyo_auth_accounts')->onDelete('set null');
      $table->index(['status', 'created_at']);
      $table->index(['user_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_withdrawal_requests');
  }
};

