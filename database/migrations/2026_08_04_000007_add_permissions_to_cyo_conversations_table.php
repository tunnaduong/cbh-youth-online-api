<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            // Who may perform each group-management action: 'owner', 'deputy' (owner+deputy),
            // or 'member' (everyone). perm_remove_members never allows 'member'.
            // perm_share_invite_link additionally allows 'none' (link sharing disabled entirely).
            $table->string('perm_change_name')->default('member')->after('invite_token');
            $table->string('perm_change_avatar')->default('member')->after('perm_change_name');
            $table->string('perm_change_background')->default('member')->after('perm_change_avatar');
            $table->string('perm_remove_members')->default('deputy')->after('perm_change_background');
            $table->string('perm_share_invite_link')->default('member')->after('perm_remove_members');
            $table->string('perm_invite_members')->default('member')->after('perm_share_invite_link');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'perm_change_name',
                'perm_change_avatar',
                'perm_change_background',
                'perm_remove_members',
                'perm_share_invite_link',
                'perm_invite_members',
            ]);
        });
    }
};
