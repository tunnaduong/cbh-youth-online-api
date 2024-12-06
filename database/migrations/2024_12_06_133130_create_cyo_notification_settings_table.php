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
        Schema::create('cyo_notification_settings', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('user_id')->index('user_id');
            $table->enum('notify_type', ['all', 'direct_mentions', 'none'])->nullable()->default('all');
            $table->boolean('notify_email_contact')->nullable()->default(true);
            $table->boolean('notify_email_marketing')->nullable()->default(false);
            $table->boolean('notify_email_social')->nullable()->default(true);
            $table->boolean('notify_email_security')->nullable()->default(true);
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_notification_settings');
    }
};
