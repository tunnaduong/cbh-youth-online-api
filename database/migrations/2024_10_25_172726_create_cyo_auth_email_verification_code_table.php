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
    public function up()
    {
        Schema::create('cyo_auth_email_verification_code', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->bigInteger('user_id')->unsigned();
            $table->string('verification_code')->unique(); // Unique verification code
            $table->timestamp('created_at')->nullable(); // Timestamp when the code was created
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Expiry time for the verification code

            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cyo_auth_email_verification_code');
    }
};
