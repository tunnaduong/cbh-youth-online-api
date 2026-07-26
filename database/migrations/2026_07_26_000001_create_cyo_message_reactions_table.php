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
        Schema::create('cyo_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('cyo_conversation_messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->string('reaction_type');
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_message_reactions');
    }
};
