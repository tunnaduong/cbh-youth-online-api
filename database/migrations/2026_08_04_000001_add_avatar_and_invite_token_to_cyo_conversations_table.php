<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('name');
            $table->string('invite_token', 64)->nullable()->unique()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'invite_token']);
        });
    }
};
