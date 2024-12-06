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
        Schema::table('cyo_volunteer_daily_reports', function (Blueprint $table) {
            $table->foreign(['class_id'], 'fk_class_id_v')->references(['id'])->on('cyo_school_classes')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['mistake_id'], 'fk_mistake_id')->references(['id'])->on('cyo_school_mistake_list')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['volunteer_id'], 'fk_volunteer_id')->references(['id'])->on('cyo_volunteers')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_volunteer_daily_reports', function (Blueprint $table) {
            $table->dropForeign('fk_class_id_v');
            $table->dropForeign('fk_mistake_id');
            $table->dropForeign('fk_volunteer_id');
        });
    }
};
