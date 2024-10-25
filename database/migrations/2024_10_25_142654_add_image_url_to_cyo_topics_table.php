<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->string('image_url')->nullable(); // Add the image_url column
        });
    }

    public function down()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropColumn('image_url'); // Drop the column if migration is rolled back
        });
    }
};
