<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // Games with this on are opened through the game proxy (cookie / save
    // sync, ad blocking) instead of straight from their own host.
    Schema::table('cyo_games', function (Blueprint $table) {
      $table->boolean('use_proxy')->default(false)->after('platform');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_games', function (Blueprint $table) {
      $table->dropColumn('use_proxy');
    });
  }
};
