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
        Schema::table('cyo_volunteers', function (Blueprint $table) {
            $table->foreign(['class_id'], 'cyo_volunteers_ibfk_1')->references(['id'])->on('cyo_school_classes')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['user_id'], 'fk_user_id_volunteers')->references(['id'])->on('cyo_auth_accounts')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_volunteers', function (Blueprint $table) {
            $table->dropForeign('cyo_volunteers_ibfk_1');
            $table->dropForeign('fk_user_id_volunteers');
        });
    }
};
