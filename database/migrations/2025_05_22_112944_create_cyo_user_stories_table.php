<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create stories table
        Schema::create('cyo_stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('content')->nullable(); // Text content
            $table->string('media_type')->nullable(); // image, video, audio
            $table->string('media_url')->nullable(); // URL to media file
            $table->string('background_color')->nullable(); // For text-only stories
            $table->string('font_style')->nullable(); // For text styling
            $table->json('text_position')->nullable(); // {x: float, y: float} for text positioning
            $table->string('privacy')->default('public'); // public, followers
            $table->timestamp('expires_at'); // Stories expire after 24 hours by default
            $table->integer('duration')->nullable(); // For videos/audio in seconds
            $table->timestamps();
            $table->softDeletes(); // For archive functionality

            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });

        // Create story viewers table
        Schema::create('cyo_story_viewers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('cyo_stories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            $table->unique(['story_id', 'user_id']); // User can view story only once
        });

        // Create story reactions table
        Schema::create('cyo_story_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reaction_type'); // like, love, haha, wow, sad, angry
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('cyo_stories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            $table->unique(['story_id', 'user_id']); // One reaction per user per story
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_story_reactions');
        Schema::dropIfExists('cyo_story_viewers');
        Schema::dropIfExists('cyo_stories');
    }
};
