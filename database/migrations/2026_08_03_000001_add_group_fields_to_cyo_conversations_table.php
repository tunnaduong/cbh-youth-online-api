<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('name');
            $table->boolean('is_public')->default(false)->after('created_by');

            $table->foreign('created_by')
                ->references('id')
                ->on('cyo_auth_accounts')
                ->nullOnDelete();
        });

        // Formalize the existing singleton public chat, which was previously identified
        // purely by matching name + type — fragile if the group is ever renamed.
        DB::table('cyo_conversations')
            ->where('name', 'Tán gẫu linh tinh')
            ->where('type', 'group')
            ->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'is_public']);
        });
    }
};
