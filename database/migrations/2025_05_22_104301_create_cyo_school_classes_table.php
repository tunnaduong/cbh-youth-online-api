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
        Schema::create('cyo_school_classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 20);
            $table->integer('grade_level');
            $table->unsignedBigInteger('main_teacher_id')->nullable()->index('fk_main_teacher_id');
            $table->integer('student_count')->default(0);
            $table->string('school_year', 20);
            $table->string('room_number', 20);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_school_classes');
    }
};
