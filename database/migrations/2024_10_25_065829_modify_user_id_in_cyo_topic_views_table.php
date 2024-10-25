<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('cyo_topic_views', function (Blueprint $table) {
            // Make user_id nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('cyo_topic_views', function (Blueprint $table) {
            // Revert back to non-nullable if needed
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
