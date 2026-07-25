<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('cover_photo')->nullable()->after('profile_picture');
            $table->foreign('cover_photo')->references('id')->on('cyo_cdn_user_content')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->dropForeign(['cover_photo']);
            $table->dropColumn('cover_photo');
        });
    }
};
