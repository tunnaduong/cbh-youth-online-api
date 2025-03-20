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
        Schema::table('cyo_school_monthly_ranking', function (Blueprint $table) {
            $table->foreign(['class_id'], 'cyo_school_monthly_ranking_ibfk_1')->references(['id'])->on('cyo_school_classes')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_school_monthly_ranking', function (Blueprint $table) {
            $table->dropForeign('cyo_school_monthly_ranking_ibfk_1');
        });
    }
};
