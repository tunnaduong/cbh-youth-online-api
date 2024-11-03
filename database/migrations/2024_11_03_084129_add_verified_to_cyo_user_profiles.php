<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->boolean('verified')->default(false); // add a boolean verified field
        });
    }

    public function down()
    {
        Schema::table('cyo_user_profiles', function (Blueprint $table) {
            $table->dropColumn('verified');
        });
    }
};
