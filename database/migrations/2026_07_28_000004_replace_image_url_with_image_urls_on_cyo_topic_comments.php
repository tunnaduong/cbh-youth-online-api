<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->json('image_urls')->nullable()->after('image_url');
        });

        // Migrate existing single image_url into image_urls array
        DB::table('cyo_topic_comments')
            ->whereNotNull('image_url')
            ->each(function ($row) {
                DB::table('cyo_topic_comments')
                    ->where('id', $row->id)
                    ->update(['image_urls' => json_encode([$row->image_url])]);
            });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('comment_html');
        });

        // Restore first image back to image_url
        DB::table('cyo_topic_comments')
            ->whereNotNull('image_urls')
            ->each(function ($row) {
                $urls = json_decode($row->image_urls, true);
                if (!empty($urls)) {
                    DB::table('cyo_topic_comments')
                        ->where('id', $row->id)
                        ->update(['image_url' => $urls[0]]);
                }
            });

        Schema::table('cyo_topic_comments', function (Blueprint $table) {
            $table->dropColumn('image_urls');
        });
    }
};
