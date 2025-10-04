<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->string('provider_id')->nullable()->after('provider');
            $table->text('provider_token')->nullable()->after('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cyo_auth_accounts', function (Blueprint $table) {
            $table->dropColumn(['provider_id', 'provider_token']);
        });
    }
};