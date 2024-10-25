<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cyo_user_saved_topics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned(); // Ensure this user_id references your users table if needed
            $table->bigInteger('topic_id')->unsigned();
            $table->timestamps();

            // Foreign key constraint referencing the id in cyo_auth_accounts table
            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
            // Update this line to reference the correct table
            $table->foreign('topic_id')->references('id')->on('cyo_topics')->onDelete('cascade');
        });
    }

    public function down()
    {
        // Schema::table('cyo_user_saved_topics', function (Blueprint $table) {
        //     // Drop foreign keys before dropping the table
        //     $table->dropForeign(['user_id']);
        //     $table->dropForeign(['topic_id']);
        // });

        Schema::dropIfExists('cyo_user_saved_topics');
    }
};
