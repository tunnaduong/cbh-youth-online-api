<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_feedback', function (Blueprint $table) {
      $table->id();
      // Nullable: guests on the web can send feedback too (they leave an email instead).
      $table->foreignId('user_id')->nullable()->constrained('cyo_auth_accounts')->nullOnDelete();
      $table->string('type', 20); // bug | suggestion | other
      $table->text('content');
      $table->json('image_urls')->nullable();
      $table->string('contact_email')->nullable();
      $table->string('platform', 20); // web | ios | android
      $table->string('app_version', 50)->nullable();
      $table->string('device_info', 255)->nullable();
      $table->string('page_url', 500)->nullable();
      $table->string('status', 20)->default('new'); // new | in_progress | resolved | closed
      $table->text('admin_notes')->nullable();
      $table->foreignId('reviewed_by')->nullable()->constrained('cyo_auth_accounts')->nullOnDelete();
      $table->timestamp('reviewed_at')->nullable();
      $table->string('ip_address', 45)->nullable();
      $table->timestamps();

      $table->index(['status', 'created_at']);
      $table->index('type');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_feedback');
  }
};
