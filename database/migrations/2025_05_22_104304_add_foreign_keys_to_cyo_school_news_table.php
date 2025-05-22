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
        Schema::table('cyo_school_news', function (Blueprint $table) {
            $table->foreign(['author_id'], 'cyo_school_news_ibfk_1')->references(['id'])->on('cyo_auth_accounts')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_school_news', function (Blueprint $table) {
            $table->dropForeign('cyo_school_news_ibfk_1');
        });
    }
};
