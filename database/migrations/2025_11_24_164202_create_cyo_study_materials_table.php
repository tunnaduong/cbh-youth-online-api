<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('cyo_study_materials', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->string('title');
      $table->text('description')->nullable();
      $table->unsignedBigInteger('category_id')->nullable();
      $table->unsignedBigInteger('file_path')->nullable(); // UserContent id
      $table->integer('price')->default(0); // in points
      $table->boolean('is_free')->default(true);
      $table->text('preview_content')->nullable();
      $table->integer('download_count')->default(0);
      $table->integer('view_count')->default(0);
      $table->enum('status', ['draft', 'published'])->default('published');
      $table->timestamps();
      $table->softDeletes();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
      $table->foreign('category_id')->references('id')->on('cyo_study_material_categories')->onDelete('set null');
      $table->foreign('file_path')->references('id')->on('cyo_cdn_user_content')->onDelete('set null');
      $table->index(['status', 'created_at']);
      $table->index(['category_id']);
      $table->index(['user_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cyo_study_materials');
  }
};

