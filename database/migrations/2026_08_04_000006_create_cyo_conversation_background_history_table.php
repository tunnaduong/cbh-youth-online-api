<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cyo_conversation_background_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('cyo_conversations')->cascadeOnDelete();
            $table->foreignId('user_content_id')->constrained('cyo_cdn_user_content')->cascadeOnDelete();
            $table->foreignId('set_by')->nullable()->constrained('cyo_auth_accounts')->nullOnDelete();
            // Bumped (not re-inserted) every time this image is re-selected as the
            // background again, so the history list can be ordered "most recently
            // used first" without piling up duplicate rows for the same image.
            $table->timestamp('used_at');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_content_id'], 'conv_bg_history_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cyo_conversation_background_history');
    }
};
