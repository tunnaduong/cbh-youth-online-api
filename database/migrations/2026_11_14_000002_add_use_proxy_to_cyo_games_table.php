<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // use_proxy: the game is opened through the game proxy (cookie / save
    // sync, ad blocking) instead of straight from its own host.
    // proxy_options: per-game proxy settings (JSON), set in GameSeeder like the
    // rest of the game's data:
    //   { "block_hosts": ["ads.example.com"], "allow_hosts": ["cdn.example.com"] }
    // block_hosts are blocked on top of the built-in list; allow_hosts are never
    // blocked for this game (use it when a blocked host makes the game hang).
    Schema::table('cyo_games', function (Blueprint $table) {
      $table->boolean('use_proxy')->default(false)->after('platform');
      $table->json('proxy_options')->nullable()->after('use_proxy');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_games', function (Blueprint $table) {
      $table->dropColumn(['use_proxy', 'proxy_options']);
    });
  }
};
