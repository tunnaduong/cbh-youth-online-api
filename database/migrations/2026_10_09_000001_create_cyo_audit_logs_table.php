<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // A record of changes: who changed what, the data before and after, and
    // where the change stands (pending -> updated / resolved).
    Schema::create('cyo_audit_logs', function (Blueprint $table) {
      $table->id();
      // The user who made the change or whom it concerns. Kept (as null)
      // when the account is deleted: the log outlives the account.
      $table->unsignedBigInteger('user_id')->nullable();
      // UPDATE_PROFILE, EDIT_POST, REPORT_BUG...
      $table->string('action_type', 50);
      $table->json('old_data')->nullable();
      $table->json('new_data')->nullable();
      // pending (waiting), updated (applied), resolved (fixed)
      $table->string('status', 20)->default('pending');
      // created_at = when it was logged; updated_at = last status change.
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->nullOnDelete();
      $table->index(['user_id', 'created_at']);
      $table->index(['action_type', 'created_at']);
      $table->index('status');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_audit_logs');
  }
};
