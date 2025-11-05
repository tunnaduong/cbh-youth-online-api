<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->integer('arrange')->nullable()->after('main_category_id');
        });

        // Populate arrange values for existing rows
        // Sort by main category arrange (asc, nulls last), then by subforum id (asc)
        // Assign sequential arrange values starting from 1 within each main category
        $subforums = DB::table('cyo_forum_subforums')
            ->join('cyo_forum_main_categories', 'cyo_forum_subforums.main_category_id', '=', 'cyo_forum_main_categories.id')
            ->select(
                'cyo_forum_subforums.id',
                'cyo_forum_subforums.main_category_id',
                'cyo_forum_main_categories.arrange as category_arrange'
            )
            ->orderByRaw('COALESCE(cyo_forum_main_categories.arrange, 999999) ASC')
            ->orderBy('cyo_forum_subforums.id', 'ASC')
            ->get();

        // Group by main_category_id and assign sequential arrange values
        $currentCategoryId = null;
        $arrangeValue = 1;

        foreach ($subforums as $subforum) {
            // If we've moved to a new category, reset arrange value to 1
            if ($currentCategoryId !== $subforum->main_category_id) {
                $currentCategoryId = $subforum->main_category_id;
                $arrangeValue = 1;
            }

            // Update the arrange value for this subforum
            DB::table('cyo_forum_subforums')
                ->where('id', $subforum->id)
                ->update(['arrange' => $arrangeValue]);

            $arrangeValue++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_forum_subforums', function (Blueprint $table) {
            $table->dropColumn('arrange');
        });
    }
};


