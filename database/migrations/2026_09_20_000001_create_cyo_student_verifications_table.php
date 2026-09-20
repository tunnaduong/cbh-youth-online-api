<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cyo_student_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('cyo_auth_accounts')->cascadeOnDelete();
            $table->string('selfie_url');
            $table->string('student_card_url');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('cyo_auth_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->timestamp('student_verified_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->dropColumn('student_verified_at');
        });
        Schema::dropIfExists('cyo_student_verifications');
    }
};
