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
        Schema::create('cyo_user_followers', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('follower_id');
            $table->unsignedBigInteger('followed_id')->index('followed_id');
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->unique(['follower_id', 'followed_id'], 'follower_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_user_followers');
    }
};
