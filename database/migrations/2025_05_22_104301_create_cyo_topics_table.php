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
        Schema::create('cyo_topics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subforum_id')->nullable()->index('cyo_topics_subforum_id_foreign');
            $table->unsignedBigInteger('user_id')->index('cyo_topics_user_id_foreign');
            $table->string('title');
            $table->text('description');
            $table->timestamps();
            $table->boolean('pinned')->default(false);
            $table->integer('hidden')->default(0);
            $table->unsignedBigInteger('cdn_image_id')->nullable()->index('cyo_topics_cdn_image_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_topics');
    }
};
