<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // Which login (personal_access_tokens.id) registered the push token, so
    // ending that login - logging out, revoking it from the devices list -
    // also stops its pushes. Null for tokens registered before this column
    // existed; they are linked the next time the app starts.
    Schema::table('cyo_expo_push_tokens', function (Blueprint $table) {
      $table->unsignedBigInteger('access_token_id')->nullable()->index();
    });
  }

  public function down(): void
  {
    Schema::table('cyo_expo_push_tokens', function (Blueprint $table) {
      $table->dropColumn('access_token_id');
    });
  }
};
