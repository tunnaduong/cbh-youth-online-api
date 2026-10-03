<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cyo_passkeys', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      // base64url credential id; it can be over a thousand characters, so
      // lookups and uniqueness go through its sha256.
      $table->text('credential_id');
      $table->string('credential_hash', 64)->unique();
      $table->text('public_key'); // PEM
      $table->unsignedBigInteger('sign_count')->default(0);
      $table->string('name', 255)->nullable();
      $table->timestamp('last_used_at')->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_passkeys');
  }
};
