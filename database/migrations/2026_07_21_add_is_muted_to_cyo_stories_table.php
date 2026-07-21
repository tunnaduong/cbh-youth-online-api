<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('cyo_stories', 'is_muted')) {
            Schema::table('cyo_stories', function (Blueprint $table) {
                $table->boolean('is_muted')->default(false)->after('pinned');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cyo_stories', 'is_muted')) {
            Schema::table('cyo_stories', function (Blueprint $table) {
                $table->dropColumn('is_muted');
            });
        }
    }
};
