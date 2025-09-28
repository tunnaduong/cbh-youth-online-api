<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        // Conversations table
        Schema::create('cyo_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('private'); // private or group
            $table->string('name')->nullable(); // for group chats
            $table->timestamps();
        });

        // Conversation participants
        Schema::create('cyo_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('cyo_conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
        });

        // Messages table
        Schema::create('cyo_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('cyo_conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->text('content');
            $table->string('type')->default('text'); // text, image, file, etc.
            $table->string('file_url')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // For message deletion
        });
    }

    public function down()
    {
        Schema::dropIfExists('cyo_messages');
        Schema::dropIfExists('cyo_conversation_participants');
        Schema::dropIfExists('cyo_conversations');
    }
};
