<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            // Drop the existing image_url column
            $table->dropColumn('image_url');

            // Add cdn_image_id foreign key column
            $table->unsignedBigInteger('cdn_image_id')->nullable(); // Adjust 'some_existing_column' as needed

            // Create foreign key constraint
            $table->foreign('cdn_image_id')
                ->references('id')
                ->on('cyo_cdn_user_content')
                ->onDelete('set null'); // Or cascade, based on your requirement
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            // Reverse the migration
            $table->string('image_url')->nullable(); // Add the old column back
            $table->dropForeign(['cdn_image_id']); // Drop the foreign key constraint
            $table->dropColumn('cdn_image_id'); // Drop the foreign key column
        });
    }
};
