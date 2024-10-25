<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->boolean('pinned')->default(false)->after('active');
        });
    }

    public function down()
    {
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->dropColumn('pinned');
        });
    }
};
