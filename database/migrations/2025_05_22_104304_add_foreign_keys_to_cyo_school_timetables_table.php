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
        Schema::table('cyo_school_timetables', function (Blueprint $table) {
            $table->foreign(['class_id'], 'fk_class_id_timetables')->references(['id'])->on('cyo_school_classes')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['teacher_id'], 'fk_teacher_id_timetables')->references(['id'])->on('cyo_school_teachers')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_school_timetables', function (Blueprint $table) {
            $table->dropForeign('fk_class_id_timetables');
            $table->dropForeign('fk_teacher_id_timetables');
        });
    }
};
