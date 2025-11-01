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
    Schema::table('cyo_messages', function (Blueprint $table) {
      // Drop the foreign key constraint first
      $table->dropForeign(['user_id']);

      // Make user_id nullable
      $table->unsignedBigInteger('user_id')->nullable()->change();

      // Add guest_name field
      $table->string('guest_name')->nullable()->after('user_id');

      // Re-add foreign key constraint but nullable
      $table->foreign('user_id')
        ->references('id')
        ->on('cyo_auth_accounts')
        ->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_messages', function (Blueprint $table) {
      // Drop foreign key
      $table->dropForeign(['user_id']);

      // Remove guest_name
      $table->dropColumn('guest_name');

      // Make user_id not nullable again
      $table->unsignedBigInteger('user_id')->nullable(false)->change();

      // Re-add foreign key constraint
      $table->foreign('user_id')
        ->references('id')
        ->on('cyo_auth_accounts')
        ->onDelete('cascade');
    });
  }
};
