<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cyo_cdn_user_content', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cyo_cdn_user_content');
    }
};
