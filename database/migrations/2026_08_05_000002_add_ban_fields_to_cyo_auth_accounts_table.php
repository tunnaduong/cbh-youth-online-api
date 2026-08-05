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
        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('role');
            $table->timestamp('banned_until')->nullable()->after('banned_at');
            $table->string('ban_reason', 255)->nullable()->after('banned_until');
            $table->unsignedBigInteger('banned_by')->nullable()->after('ban_reason');

            $table->foreign('banned_by')->references('id')->on('cyo_auth_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropColumn(['banned_at', 'banned_until', 'ban_reason', 'banned_by']);
        });
    }
};
