<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_shop_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_shop_orders', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }
};
