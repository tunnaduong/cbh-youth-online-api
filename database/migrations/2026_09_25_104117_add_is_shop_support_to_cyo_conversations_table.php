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
        Schema::table('cyo_conversations', function (Blueprint $table) {
            // Marks the one ongoing group thread between a customer and every
            // shop admin, created by ShopController::contactShop() - lets that
            // endpoint find/reuse the existing thread instead of creating a
            // new one on every "Nhắn tin" click, and lets ChatController let
            // any admin post/read there even before they're attached as a
            // participant (see the is_shop_support checks in getMessages/sendMessage).
            $table->boolean('is_shop_support')->default(false)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_conversations', function (Blueprint $table) {
            $table->dropColumn('is_shop_support');
        });
    }
};
