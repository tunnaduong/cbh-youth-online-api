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
        Schema::create('cyo_auth_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username');
            $table->string('password')->nullable();
            $table->string('email')->nullable();
            $table->enum('role', ['user', 'student', 'teacher', 'volunteer', 'admin'])->default('user');
            $table->string('provider', 11)->nullable();
            $table->timestamps();
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_auth_accounts');
    }
};
