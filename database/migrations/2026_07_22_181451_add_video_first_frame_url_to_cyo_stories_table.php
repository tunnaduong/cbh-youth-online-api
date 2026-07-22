<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cyo_stories', function (Blueprint $table) {
            $table->string('video_first_frame_url')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_stories', function (Blueprint $table) {
            $table->dropColumn('video_first_frame_url');
        });
    }
};
