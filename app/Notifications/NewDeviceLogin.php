<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the account owner that the account was just logged in to from a
 * device it has not been used on before.
 */
class NewDeviceLogin extends Notification
{
  private const PLATFORM_LABELS = [
    'web' => 'Web',
    'ios' => 'Ứng dụng iOS',
    'android' => 'Ứng dụng Android',
  ];

  protected $details;
  protected $time;

  /**
   * @param  array  $details  platform, app_version, device_name, device_model (all optional)
   * @param  \Illuminate\Support\Carbon  $time
   */
  public function __construct($details, $time)
  {
    $this->details = $details;
    $this->time = $time;
  }

  public function via($notifiable)
  {
    return ['mail'];
  }

  public function toMail($notifiable)
  {
    $frontendUrl = env('APP_UI_URL', 'https://chuyenbienhoa.com');

    $platform = self::PLATFORM_LABELS[$this->details['platform'] ?? ''] ?? null;
    if ($platform && !empty($this->details['app_version'])) {
      $platform .= ' ' . $this->details['app_version'];
    }

    $device = implode(' · ', array_filter([
      $this->details['device_name'] ?? null,
      $this->details['device_model'] ?? null,
      $platform,
    ])) ?: 'Thiết bị không xác định';

    return (new MailMessage)
      ->subject('Đăng nhập mới vào tài khoản CBH Youth Online của bạn')
      ->line('Tài khoản **' . $notifiable->username . '** vừa được đăng nhập trên một thiết bị mới.')
      ->line('**Thiết bị:** ' . $device)
      ->line('**Thời gian:** ' . $this->time->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') . ' (giờ Việt Nam)')
      ->line('Nếu đây là bạn, bạn không cần làm gì thêm.')
      ->line('Nếu không phải bạn, hãy đổi mật khẩu ngay và đăng xuất các thiết bị lạ trong phần Cài đặt.')
      ->action('Mở Cài đặt tài khoản', $frontendUrl . '/settings?tab=account')
      ->salutation("Trân trọng,  \r\nĐội ngũ CBH Youth Online");
  }
}
