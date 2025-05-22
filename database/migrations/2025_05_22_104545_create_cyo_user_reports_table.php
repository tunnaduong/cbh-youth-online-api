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
        Schema::create('cyo_user_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reported_user_id');
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            $table->foreign('reported_user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('cyo_topics')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('cyo_auth_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_user_reports');
    }
};
