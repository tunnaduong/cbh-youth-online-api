<?php

namespace App\Mail;

use App\Models\User;
use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentVerificationApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Xác minh học sinh của bạn đã được duyệt thành công');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.student_verification_approved',
            with: [
                'recipientName' => $this->recipient->name,
                'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrlForEmail($this->recipient->email),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
