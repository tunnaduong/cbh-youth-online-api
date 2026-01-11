<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use SePay\SePay\Events\SePayWebhookEvent;

class SePayPaymentListener
{
  /**
   * Create the event listener.
   */
  public function __construct()
  {
    //
  }

  /**
   * Handle the event.
   */
  public function handle(SePayWebhookEvent $event): void
  {
    $data = $event->sePayWebhookData;

    // Chỉ xử lý tiền vào (transferType = 'in')
    if ($data->transferType !== 'in') {
      return;
    }

    // Tìm User ID trong nội dung chuyển khoản.
    // Pattern mới: MW<UserID><Timestamp(10)> (ví dụ MW451704067200)
    if (preg_match('/MW(\d+)/i', $data->content, $matches)) {
      $rawId = $matches[1];

      // Nếu độ dài chuỗi số > 10, giả định 10 số cuối là timestamp unix, phần trước là UserID.
      if (strlen($rawId) > 10) {
        $userId = (int) substr($rawId, 0, -10);
      } else {
        $userId = (int) $rawId;
      }

      // Chuyển đổi VND sang Points (1000 VND = 10 Points)
      $points = \App\Services\PointsService::convertVNDToPoints($data->transferAmount);

      // Cộng điểm cho user
      $success = \App\Services\PointsService::addPoints(
        $userId,
        $points,
        'deposit',
        "Nạp tiền tự động qua SePay (Mã GD: #{$data->id})",
        $data->id  // ID giao dịch SePay để làm tham chiếu
      );

      if ($success) {
        \Illuminate\Support\Facades\Log::info("SePay: Added {$points} points to user {$userId} from transaction #{$data->id}");

        // Gửi thông báo cho user
        try {
          \App\Services\NotificationService::createSystemNotification(
            $userId,
            'payment_received',
            [
              'title' => 'Nạp tiền thành công',
              'message' => 'Hệ thống đã nhận được ' . number_format($data->transferAmount) . "đ và cộng {$points} điểm vào ví của bạn.",
              'url' => '/wallet'
            ]
          );
        } catch (\Exception $e) {
          \Illuminate\Support\Facades\Log::error('SePay Notification Error: ' . $e->getMessage());
        }
      } else {
        \Illuminate\Support\Facades\Log::error("SePay: Failed to add points for user {$userId}");
      }
    } else {
      \Illuminate\Support\Facades\Log::warning("SePay: Could not find User ID in content: {$data->content}");
    }
  }
}
