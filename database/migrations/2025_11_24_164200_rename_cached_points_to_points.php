<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // Use raw SQL for better compatibility across database drivers
    if (DB::getDriverName() === 'mysql') {
      DB::statement('ALTER TABLE cyo_auth_accounts CHANGE cached_points points INTEGER DEFAULT 0');
    } else {
      // For other databases, add new column, copy data, drop old column
      Schema::table('cyo_auth_accounts', function (Blueprint $table) {
        $table->integer('points')->default(0)->after('role');
      });
      
      DB::statement('UPDATE cyo_auth_accounts SET points = cached_points');
      
      Schema::table('cyo_auth_accounts', function (Blueprint $table) {
        $table->dropColumn('cached_points');
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (DB::getDriverName() === 'mysql') {
      DB::statement('ALTER TABLE cyo_auth_accounts CHANGE points cached_points INTEGER DEFAULT 0');
    } else {
      Schema::table('cyo_auth_accounts', function (Blueprint $table) {
        $table->integer('cached_points')->default(0)->after('role');
      });
      
      DB::statement('UPDATE cyo_auth_accounts SET cached_points = points');
      
      Schema::table('cyo_auth_accounts', function (Blueprint $table) {
        $table->dropColumn('points');
      });
    }
  }
};

