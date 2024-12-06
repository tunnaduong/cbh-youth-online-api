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
        Schema::table('cyo_school_classes', function (Blueprint $table) {
            $table->foreign(['main_teacher_id'], 'fk_main_teacher_id')->references(['id'])->on('cyo_school_teachers')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_school_classes', function (Blueprint $table) {
            $table->dropForeign('fk_main_teacher_id');
        });
    }
};
