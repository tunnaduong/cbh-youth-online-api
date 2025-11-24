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
    Schema::create('cyo_points_transactions', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'earning', 'post', 'vote', 'comment']);
      $table->integer('amount'); // points (can be positive or negative)
      $table->string('sepay_transaction_id')->nullable();
      $table->string('reference_code')->nullable();
      $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
      $table->text('description')->nullable();
      $table->unsignedBigInteger('related_id')->nullable(); // id of post/vote/comment/purchase if applicable
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->index(['user_id', 'created_at']);
      $table->index(['type']);
      $table->index(['sepay_transaction_id']);
      $table->index(['reference_code']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_points_transactions');
  }
};

