<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // A customer's delivery details, saved by the gift shop assistant once it
    // has collected them in the support chat, and handed back to it on the
    // next order so the customer isn't asked again (see ShopAddress).
    Schema::create('cyo_shop_addresses', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->string('recipient_name', 120);
      $table->string('phone', 20);
      // The full line as it goes on an order.
      $table->string('address', 500);
      // The same address split up by the assistant, where it could tell the
      // parts apart. All optional.
      $table->string('place', 200)->nullable();
      $table->string('street', 200)->nullable();
      $table->string('ward', 120)->nullable();
      $table->string('district', 120)->nullable();
      $table->string('province', 120)->nullable();
      // Lower-cased name|phone|address, to recognise the same details again.
      $table->string('fingerprint', 64);
      $table->timestamp('last_used_at')->nullable();
      $table->timestamps();

      $table->unique(['user_id', 'fingerprint']);
      $table->foreign('user_id')->references('id')->on('cyo_auth_accounts')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_shop_addresses');
  }
};
