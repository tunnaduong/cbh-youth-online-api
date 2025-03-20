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
        Schema::create('cyo_forum_subforums', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('main_category_id')->index('cyo_forum_subforums_main_category_id_foreign');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('slug', 99)->nullable();
            $table->enum('role_restriction', ['admin', 'moderator', 'user', ''])->default('user');
            $table->boolean('active')->default(true);
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_forum_subforums');
    }
};
