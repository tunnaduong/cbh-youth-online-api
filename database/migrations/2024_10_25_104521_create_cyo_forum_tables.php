<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Main Categories
        Schema::create('cyo_forum_main_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Subforums (belonging to Main Categories)
        Schema::create('cyo_forum_subforums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_category_id')->constrained('cyo_forum_main_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        // Drop subforums
        Schema::dropIfExists('cyo_forum_subforums');

        // Drop main categories
        Schema::dropIfExists('cyo_forum_main_categories');
    }
};
