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
            $table->id(); // Primary key
            $table->unsignedBigInteger('auth_account_id'); // Foreign key to cyo_auth_accounts
            $table->string('profile_name')->nullable(); // Bio of the user
            $table->string('profile_username')->nullable(); // Bio of the user
            $table->string('bio')->nullable(); // Bio of the user
            $table->string('profile_picture')->nullable(); // URL to the profile picture
            $table->date('birthday')->nullable(); // User's birthday
            $table->string('gender')->nullable(); // Gender
            $table->string('location')->nullable(); // Location of the user
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('auth_account_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
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
