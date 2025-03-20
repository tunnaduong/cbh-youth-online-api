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
        Schema::create('cyo_user_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('auth_account_id')->index('cyo_user_profiles_auth_account_id_foreign');
            $table->string('profile_name')->nullable();
            $table->string('profile_username')->nullable();
            $table->string('bio')->nullable();
            $table->unsignedBigInteger('profile_picture')->nullable()->index('cyo_user_profiles_profile_picture_foreign');
            $table->string('oauth_profile_picture', 999)->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->enum('verified', ['0', '1'])->default('0');
            $table->dateTime('last_username_change')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_user_profiles');
    }
};
