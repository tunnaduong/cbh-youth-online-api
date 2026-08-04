<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->foreignId('background_content_id')
                ->nullable()
                ->after('avatar_url')
                ->constrained('cyo_cdn_user_content')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->dropForeign(['background_content_id']);
            $table->dropColumn('background_content_id');
        });
    }
};
