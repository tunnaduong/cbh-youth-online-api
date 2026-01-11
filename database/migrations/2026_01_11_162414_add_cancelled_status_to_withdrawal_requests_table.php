<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    DB::statement("ALTER TABLE cyo_withdrawal_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    DB::statement("ALTER TABLE cyo_withdrawal_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending'");
  }
};
