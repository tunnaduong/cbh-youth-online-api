<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cyo_moderation_queue', function (Blueprint $table) {
            $table->id();
            $table->string('content_type'); // 'topic' | 'comment'
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content_snapshot'); // title + body tại thời điểm submit
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('ai_verdict')->nullable(); // 'approved' | 'rejected' | 'needs_review'
            $table->text('ai_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cyo_moderation_queue');
    }
};
