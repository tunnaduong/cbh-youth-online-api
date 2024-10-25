<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            // Update the profile_picture column to be an unsigned big integer
            $table->unsignedBigInteger('profile_picture')->nullable()->change();

            // Add foreign key to reference cyo_cdn_user_content
            $table->foreign('profile_picture')->references('id')->on('cyo_cdn_user_content')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            // Remove the foreign key constraint
            $table->dropForeign(['profile_picture']);

            // Change the profile_picture back if needed
            $table->integer('profile_picture')->nullable()->change();
        });
    }
};
