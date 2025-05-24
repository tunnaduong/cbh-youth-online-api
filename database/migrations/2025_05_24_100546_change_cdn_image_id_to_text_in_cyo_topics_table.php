<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeCdnImageIdToTextInCyoTopicsTable extends Migration
{
    public function up()
    {
        // Get all foreign key constraints for the table
        $foreignKeys = $this->getForeignKeyConstraints('cyo_topics');

        Schema::table('cyo_topics', function (Blueprint $table) use ($foreignKeys) {
            // Drop foreign key if it exists
            foreach ($foreignKeys as $foreignKey) {
                if (str_contains($foreignKey->CONSTRAINT_NAME, 'cdn_image_id')) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            }
        });

        Schema::table('cyo_topics', function (Blueprint $table) {
            // Change column type to string with a reasonable length that works with MySQL indexes
            $table->string('cdn_image_id', 255)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            // Change back to unsignedBigInteger
            $table->unsignedBigInteger('cdn_image_id')->nullable()->change();

            // Restore foreign key
            $table->foreign('cdn_image_id')
                  ->references('id')
                  ->on('cdn_images')
                  ->onDelete('set null');
        });
    }

    /**
     * Get all foreign key constraint names for a table
     */
    private function getForeignKeyConstraints($tableName)
    {
        $database = DB::getDatabaseName();

        return DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND TABLE_SCHEMA = '{$database}'
            AND TABLE_NAME = '{$tableName}'
        ");
    }
}
