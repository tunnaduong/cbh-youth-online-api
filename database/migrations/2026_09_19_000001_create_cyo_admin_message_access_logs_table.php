<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // Audit trail: every time an admin opens a user's conversation or searches messages.
    Schema::create('cyo_admin_message_access_logs', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('admin_id');
      $table->unsignedBigInteger('conversation_id')->nullable();
      $table->string('action'); // view_conversation | search_messages
      $table->string('query')->nullable();
      $table->string('ip', 45)->nullable();
      $table->timestamps();

      $table->index(['admin_id', 'created_at']);
      $table->index('conversation_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_admin_message_access_logs');
  }
};
