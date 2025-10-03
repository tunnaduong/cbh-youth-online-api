<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Custom notification for email verification.
 *
 * This class extends the default VerifyEmail notification to provide a custom
 * email message and subject.
 */
class VerifyEmail extends BaseVerifyEmail
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  string  $url The verification URL.
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Xác minh địa chỉ email của bạn')
            ->line('Vui lòng nhấp vào nút bên dưới để xác minh địa chỉ email của bạn.')
            ->action('Xác minh địa chỉ email', $url)
            ->line('Nếu bạn không tạo tài khoản, bạn có thể bỏ qua email này.')
            ->salutation("Trân trọng,  \r\nĐội ngũ CBH Youth Online");
    }
}
