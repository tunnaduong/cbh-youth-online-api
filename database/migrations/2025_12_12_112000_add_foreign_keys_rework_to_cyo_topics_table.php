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
        Schema::table('cyo_topics', function (Blueprint $table) {
            // First, ensure the CDN columns are of the correct type (unsignedBigInteger)
            // We use change() to modify existing columns.
            $table->unsignedBigInteger('cdn_image_id')->nullable()->change();
            $table->unsignedBigInteger('cdn_document_id')->nullable()->change();
        });

        Schema::table('cyo_topics', function (Blueprint $table) {
            // Add Foreign Keys. 
            // We explicitely name them to match standard conventions and allow checking/dropping later.
            
            // Checks if keys exist before adding would be ideal, but Laravel migration syntax 
            // is declarative. We assume they are missing based on user request.
            // If they exist, this might throw an error, but that's standard for migrations 
            // attempting to duplicately creat constraints.
            
            // subforum_id
            if (! $this->foreignKeyExists('cyo_topics', 'cyo_topics_subforum_id_foreign')) {
                 $table->foreign('subforum_id', 'cyo_topics_subforum_id_foreign')
                    ->references('id')->on('cyo_forum_subforums')
                    ->onUpdate('cascade')->onDelete('cascade');
            }

            // user_id
            if (! $this->foreignKeyExists('cyo_topics', 'cyo_topics_user_id_foreign')) {
                $table->foreign('user_id', 'cyo_topics_user_id_foreign')
                    ->references('id')->on('cyo_auth_accounts')
                    ->onUpdate('cascade')->onDelete('cascade');
            }

            // cdn_image_id
            if (! $this->foreignKeyExists('cyo_topics', 'cyo_topics_cdn_image_id_foreign')) {
                $table->foreign('cdn_image_id', 'cyo_topics_cdn_image_id_foreign')
                    ->references('id')->on('cyo_cdn_user_content')
                    ->onUpdate('cascade')->onDelete('set null');
            }

            // cdn_document_id
            if (! $this->foreignKeyExists('cyo_topics', 'cyo_topics_cdn_document_id_foreign')) {
                $table->foreign('cdn_document_id', 'cyo_topics_cdn_document_id_foreign')
                    ->references('id')->on('cyo_cdn_user_content')
                    ->onUpdate('cascade')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyo_topics', function (Blueprint $table) {
            $table->dropForeign(['cyo_topics_subforum_id_foreign']);
            $table->dropForeign(['cyo_topics_user_id_foreign']);
            $table->dropForeign(['cyo_topics_cdn_image_id_foreign']);
            $table->dropForeign(['cyo_topics_cdn_document_id_foreign']);
        });

        Schema::table('cyo_topics', function (Blueprint $table) {
             // Revert types if necessary. 
             // Note: Reverting exactly to 'string' for cdn_image_id/document_id is ambiguous 
             // without knowing previous state perfectly, but we can assume string/text based on history.
             $table->string('cdn_image_id', 255)->nullable()->change();
             $table->string('cdn_document_id', 255)->nullable()->change();
        });
    }

    /**
     * Helper to check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $name): bool
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ? 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name]
        );

        return count($constraints) > 0;
    }
};
