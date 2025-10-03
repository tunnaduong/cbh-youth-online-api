<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable class for sending a password reset email.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The user's email address.
     *
     * @var string
     */
    public $email;

    /**
     * Create a new message instance.
     *
     * @param  string  $token
     * @param  string  $email
     * @return void
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.reset_password')
            ->subject('Thiết lập lại mật khẩu của bạn')
            ->with([
                'url' => $this->resetUrl(),
            ]);
    }

    /**
     * Generate the reset password URL.
     *
     * @return string
     */
    protected function resetUrl()
    {
        return env("APP_UI_URL", "http://localhost:3000") . '/password/reset/' . $this->token . '?email=' . $this->email;
    }
}
