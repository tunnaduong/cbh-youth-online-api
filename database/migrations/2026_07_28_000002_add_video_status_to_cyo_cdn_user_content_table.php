<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_cdn_user_content', function (Blueprint $table) {
            $table->string('video_status')->nullable()->after('file_size'); // pending, processing, completed, failed
        });
    }

    public function down(): void
    {
        Schema::table('cyo_cdn_user_content', function (Blueprint $table) {
            $table->dropColumn('video_status');
        });
    }
};
