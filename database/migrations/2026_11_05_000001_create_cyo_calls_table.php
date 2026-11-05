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
    Schema::create('cyo_calls', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('conversation_id');
      $table->unsignedBigInteger('caller_id');
      $table->unsignedBigInteger('receiver_id')->nullable();
      $table->enum('type', ['audio', 'video'])->default('audio');
      $table->enum('status', [
        'ringing',
        'accepted',
        'rejected',
        'busy',
        'ended',
        'missed',
        'cancelled',
      ])->default('ringing');
      $table->timestamp('started_at')->nullable();
      $table->timestamp('accepted_at')->nullable();
      $table->timestamp('ended_at')->nullable();
      $table->unsignedInteger('duration')->nullable(); // in seconds
      $table->string('end_reason')->nullable(); // completed, declined, missed, busy, cancelled, timeout
      $table->json('metadata')->nullable();
      $table->timestamps();

      $table->foreign('conversation_id')
        ->references('id')
        ->on('cyo_conversations')
        ->onDelete('cascade');

      $table->foreign('caller_id')
        ->references('id')
        ->on('cyo_auth_accounts')
        ->onDelete('cascade');

      $table->foreign('receiver_id')
        ->references('id')
        ->on('cyo_auth_accounts')
        ->onDelete('set null');

      $table->index(['conversation_id', 'status']);
      $table->index(['caller_id', 'status']);
      $table->index(['receiver_id', 'status']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_calls');
  }
};
