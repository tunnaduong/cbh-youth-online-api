<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('cyo_audit_logs', function (Blueprint $table) {
      // Who did it, when that is not the user it concerns (an admin editing
      // someone's account). Null = the user themselves, or the system.
      $table->unsignedBigInteger('actor_id')->nullable()->after('user_id');
      // What was changed: "topic" + its id, "profile" + its id...
      $table->string('target_type', 40)->nullable()->after('action_type');
      $table->unsignedBigInteger('target_id')->nullable()->after('target_type');

      $table->foreign('actor_id')->references('id')->on('cyo_auth_accounts')->nullOnDelete();
      $table->index(['target_type', 'target_id']);
    });
  }

  public function down(): void
  {
    Schema::table('cyo_audit_logs', function (Blueprint $table) {
      $table->dropForeign(['actor_id']);
      $table->dropIndex(['target_type', 'target_id']);
      $table->dropColumn(['actor_id', 'target_type', 'target_id']);
    });
  }
};
