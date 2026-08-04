<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_notification_settings', function (Blueprint $table) {
            $table->boolean('chat_read_receipts')->default(true)->after('notify_email_security');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_notification_settings', function (Blueprint $table) {
            $table->dropColumn('chat_read_receipts');
        });
    }
};
