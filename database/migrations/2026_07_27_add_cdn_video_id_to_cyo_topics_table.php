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
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->text('cdn_video_id')->nullable()->after('is_muted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropColumn('cdn_video_id');
        });
    }
};
