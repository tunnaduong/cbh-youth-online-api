<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('subforum_id')->nullable()->after('id'); // Thêm trường subforum_id
            $table->foreign('subforum_id')->references('id')->on('cyo_forum_subforums')->onDelete('cascade'); // Thêm khóa ngoại
        });
    }

    public function down()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropForeign(['subforum_id']); // Xóa khóa ngoại
            $table->dropColumn('subforum_id'); // Xóa trường subforum_id
        });
    }
};
