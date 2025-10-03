<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable class for sending an email verification link.
 */
class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The user account instance.
     *
     * @var \App\Models\AuthAccount
     */
    public $account;

    /**
     * The email verification code.
     *
     * @var string
     */
    public $verificationCode;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\AuthAccount  $account
     * @param  string  $verificationCode
     * @return void
     */
    public function __construct($account, $verificationCode)
    {
        $this->account = $account;
        $this->verificationCode = $verificationCode;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $verificationUrl = env('APP_UI_URL', 'http://localhost:3000') . '/email/verify/' . $this->verificationCode;
        
        return $this->view('emails.verify')
            ->subject('Xác minh địa chỉ email của bạn')
            ->with([
                'account' => $this->account,
                'verificationCode' => $this->verificationCode,
                'verificationUrl' => $verificationUrl
            ]);
    }
}
