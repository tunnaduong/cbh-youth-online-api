<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cyo_print_requests', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('cyo_auth_accounts')->cascadeOnDelete();
      $table->string('title');
      $table->text('description')->nullable();
      $table->integer('file_id')->nullable();
      $table->integer('pages_count')->default(0);
      $table->string('delivery_method'); // 'delivery_to_class' | 'pickup_at_office'
      $table->string('class_or_location')->nullable(); // Tên lớp hoặc địa chỉ pickup
      $table->text('notes')->nullable();
      $table->enum('status', [
        'pending',      // Chờ duyệt
        'approved',     // Được duyệt
        'printing',     // Đang in
        'ready',        // Sẵn sàng giao/nhận
        'delivered',    // Đã giao
        'picked_up',    // Đã nhận
        'cancelled',    // Hủy
        'rejected',     // Từ chối
      ])->default('pending');
      $table->foreignId('assigned_staff_id')->nullable()->constrained('cyo_auth_accounts')->nullOnDelete();
      $table->dateTime('approved_at')->nullable();
      $table->dateTime('printing_started_at')->nullable();
      $table->dateTime('ready_at')->nullable();
      $table->dateTime('delivered_at')->nullable();
      $table->dateTime('picked_up_at')->nullable();
      $table->text('rejection_reason')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cyo_print_requests');
  }
};
