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
        Schema::create('cyo_recording_views', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id')->index('fk_record_id_recording');
            $table->unsignedBigInteger('user_id')->nullable()->index('fk_user_id_record_view');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_recording_views');
    }
};
