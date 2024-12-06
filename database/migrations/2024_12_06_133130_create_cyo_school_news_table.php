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
        Schema::create('cyo_school_news', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('title');
            $table->text('content');
            $table->unsignedBigInteger('author_id')->index('author_id');
            $table->dateTime('published_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->enum('status', ['draft', 'published', 'archived'])->nullable()->default('draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_school_news');
    }
};
