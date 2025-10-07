<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Custom notification for password reset.
 *
 * This class provides a custom email message and subject for password reset emails.
 */
class ResetPassword extends Notification
{
  use Queueable;

  protected $token;
  protected $email;

  /**
   * Create a new notification instance.
   *
   * @param  string  $token
   * @param  string  $email
   */
  public function __construct($token, $email)
  {
    $this->token = $token;
    $this->email = $email;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @param  mixed  $notifiable
   * @return array
   */
  public function via($notifiable)
  {
    return ['mail'];
  }

  /**
   * Get the mail representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return \Illuminate\Notifications\Messages\MailMessage
   */
  public function toMail($notifiable)
  {
    $url = env('APP_UI_URL') . '/password/reset/' . $this->token . '?email=' . urlencode($this->email);

    return (new MailMessage)
      ->subject('Đặt lại mật khẩu của bạn')
      ->line('Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
      ->action('Đặt lại mật khẩu', $url)
      ->line('Liên kết đặt lại mật khẩu này sẽ hết hạn sau 60 phút.')
      ->line('Nếu bạn không yêu cầu đặt lại mật khẩu, bạn có thể bỏ qua email này.')
      ->salutation("Trân trọng,  \r\nĐội ngũ CBH Youth Online");
  }

  /**
   * Get the array representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return array
   */
  public function toArray($notifiable)
  {
    return [
      //
    ];
  }
}
