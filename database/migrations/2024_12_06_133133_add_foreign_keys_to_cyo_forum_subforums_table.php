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
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->foreign(['main_category_id'])->references(['id'])->on('cyo_forum_main_categories')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->dropForeign('cyo_forum_subforums_main_category_id_foreign');
        });
    }
};
