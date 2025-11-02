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
        Schema::create('cyo_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->foreignId('actor_id')->nullable()->constrained('cyo_auth_accounts')->onDelete('set null');
            $table->string('type'); // topic_liked, comment_liked, comment_replied, etc.
            $table->string('notifiable_type')->nullable(); // Topic, TopicComment, etc.
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->json('data')->nullable(); // Additional data (title, excerpt, url, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('read_at');
            $table->index('type');
            $table->index(['user_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_notifications');
    }
};
