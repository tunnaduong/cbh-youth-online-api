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
        Schema::create('cyo_recordings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('fk_user_id');
            $table->string('title');
            $table->mediumText('description');
            $table->unsignedBigInteger('cdn_audio_id')->index('fk_cdn_audio_id');
            $table->unsignedBigInteger('cdn_preview_id')->nullable()->index('fk_cdn_preview_id');
            $table->string('audio_length', 11);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_recordings');
    }
};
