<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->softDeletes(); // tạo cột deleted_at nullable
        });
    }

    public function down()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
