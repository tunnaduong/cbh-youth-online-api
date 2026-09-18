<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_admin_broadcasts', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('admin_id');
      $table->string('title');
      $table->text('body');
      $table->string('url')->nullable();
      $table->unsignedBigInteger('topic_id')->nullable();
      $table->string('audience'); // all | role | users
      $table->json('audience_value')->nullable(); // role name or list of user ids
      $table->json('channels'); // ["web", "mobile"]
      $table->boolean('save_to_inbox')->default(true);
      $table->string('status')->default('queued'); // queued | sending | sent | failed
      $table->unsignedInteger('recipients_count')->default(0);
      $table->unsignedInteger('web_sent')->default(0);
      $table->unsignedInteger('mobile_sent')->default(0);
      $table->text('error')->nullable();
      $table->timestamp('sent_at')->nullable();
      $table->timestamps();

      $table->index('created_at');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_admin_broadcasts');
  }
};
