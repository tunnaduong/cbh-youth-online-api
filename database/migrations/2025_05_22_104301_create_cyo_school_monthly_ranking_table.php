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
        Schema::create('cyo_school_monthly_ranking', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('class_id')->index('class_id');
            $table->string('class_name');
            $table->integer('month');
            $table->integer('year');
            $table->integer('total_points')->nullable()->default(0);
            $table->integer('rank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_school_monthly_ranking');
    }
};
