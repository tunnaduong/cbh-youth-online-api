<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    DB::statement("ALTER TABLE cyo_points_transactions MODIFY COLUMN type ENUM('deposit', 'withdrawal', 'purchase', 'earning', 'post', 'vote', 'comment', 'game')");
  }

  public function down(): void
  {
    DB::statement("ALTER TABLE cyo_points_transactions MODIFY COLUMN type ENUM('deposit', 'withdrawal', 'purchase', 'earning', 'post', 'vote', 'comment')");
  }
};
