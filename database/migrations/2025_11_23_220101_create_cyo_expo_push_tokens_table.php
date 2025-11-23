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
        Schema::create('cyo_expo_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->onDelete('cascade');
            $table->string('expo_push_token')->unique();
            $table->string('device_type')->nullable(); // 'ios', 'android'
            $table->string('device_id')->nullable(); // Optional device identifier
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('expo_push_token');
            $table->index('is_active');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_expo_push_tokens');
    }
};

