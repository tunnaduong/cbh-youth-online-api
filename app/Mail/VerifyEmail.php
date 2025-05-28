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
        $verificationUrl = env('APP_UI_URL', 'http://localhost:3000') . '/verify-email/' . $this->account->id . '/' . $this->verificationCode;
        
        return $this->view('emails.verify')
            ->subject('Xác minh địa chỉ email của bạn')
            ->with([
                'account' => $this->account,
                'verificationCode' => $this->verificationCode,
                'verificationUrl' => $verificationUrl
            ]);
    }
}
