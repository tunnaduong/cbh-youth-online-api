<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('cyo_stories', function (Blueprint $table) {
      $table->boolean('deleted_by_user')->default(false)->after('deleted_at');
    });
  }

  public function down(): void
  {
    Schema::table('cyo_stories', function (Blueprint $table) {
      $table->dropColumn('deleted_by_user');
    });
  }
};
