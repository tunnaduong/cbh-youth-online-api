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
    Schema::table('cyo_shop_orders', function (Blueprint $table) {
      // points: paid instantly out of the user's CBH activity points balance
      // qr: instant bank-transfer QR (SePay), matches the wallet deposit flow
      // cod: pay the courier on delivery
      $table->enum('payment_method', ['points', 'qr', 'cod'])->default('cod')->after('status');
      $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('payment_method');
      // Unique code embedded in the QR transfer content (format: GS<order_id><timestamp>,
      // mirrors the wallet's MW<user_id><timestamp> deposit_code) so the SePay
      // webhook can match an incoming bank transfer back to this order.
      $table->string('payment_code')->nullable()->unique()->after('payment_status');
      $table->timestamp('paid_at')->nullable()->after('payment_code');

      $table->index(['payment_method', 'payment_status']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('cyo_shop_orders', function (Blueprint $table) {
      $table->dropIndex(['payment_method', 'payment_status']);
      $table->dropColumn(['payment_method', 'payment_status', 'payment_code', 'paid_at']);
    });
  }
};
