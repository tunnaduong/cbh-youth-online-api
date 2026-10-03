<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a one-time two-factor code (login challenge, or confirming a
 * change to the two-factor settings).
 */
class TwoFactorCode extends Notification
{
  protected $code;
  protected $minutes;

  /**
   * @param  string  $code
   * @param  int  $minutes  How long the code stays valid.
   */
  public function __construct($code, $minutes)
  {
    $this->code = $code;
    $this->minutes = $minutes;
  }

  public function via($notifiable)
  {
    return ['mail'];
  }

  public function toMail($notifiable)
  {
    return (new MailMessage)
      ->subject('Mã xác thực hai lớp CBH Youth Online')
      ->line('Mã xác thực hai lớp cho tài khoản CBH Youth Online của bạn là:')
      ->line('**' . $this->code . '**')
      ->line('Mã có hiệu lực trong ' . $this->minutes . ' phút. Không chia sẻ mã này cho bất kỳ ai.')
      ->line('Nếu bạn không yêu cầu mã này, hãy đổi mật khẩu ngay vì có thể ai đó đang biết mật khẩu của bạn.')
      ->salutation("Trân trọng,  \r\nĐội ngũ CBH Youth Online");
  }
}
