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
        Schema::create('cyo_online_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id');
            $table->unsignedBigInteger('user_id')->nullable()->index('ibfk_user_id_online');
            $table->boolean('is_hidden')->nullable()->default(false);
            $table->dateTime('last_activity');
            $table->string('ip_address', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_online_users');
    }
};
