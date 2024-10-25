<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $account;
    public $verificationCode;

    public function __construct($account, $verificationCode)
    {
        $this->account = $account;
        $this->verificationCode = $verificationCode;
    }

    public function build()
    {
        return $this->view('emails.verify') // Specify your email view here
            ->subject('Xác minh địa chỉ email của bạn')
            ->with([
                'verificationUrl' => route('verification.verify', $this->verificationCode),
            ]);
    }
}
