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
        // Bảng danh mục diễn đàn
        Schema::create('cyo_forum_main_categories', function (Blueprint $table) {
            $table->id();
            $table->integer('arrange')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->string('role_restriction')->default('user');
            $table->string('background_image')->nullable();
            $table->timestamps();
        });

        // Bảng diễn đàn con
        Schema::create('cyo_forum_subforums', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('cyo_forum_main_categories')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('moderator_id')->nullable()->constrained('cyo_auth_accounts')->onDelete('set null');
            $table->foreignId('last_post_id')->nullable();
            $table->timestamps();
        });

        // Bảng bài viết
        Schema::create('cyo_forum_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->foreignId('subforum_id')->constrained('cyo_forum_subforums')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->foreignId('last_reply_id')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('reply_count')->default(0);
            $table->timestamps();
        });

        // Bảng trả lời bài viết
        Schema::create('cyo_forum_replies', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->foreignId('post_id')->constrained('cyo_forum_posts')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->timestamps();
        });

        // Cập nhật khóa ngoại cho last_post_id và last_reply_id
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->foreign('last_post_id')->references('id')->on('cyo_forum_posts')->onDelete('set null');
        });

        Schema::table('cyo_forum_posts', function (Blueprint $table) {
            $table->foreign('last_reply_id')->references('id')->on('cyo_forum_replies')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_forum_replies');
        Schema::dropIfExists('cyo_forum_posts');
        Schema::dropIfExists('cyo_forum_subforums');
        Schema::dropIfExists('cyo_forum_main_categories');
    }
};
