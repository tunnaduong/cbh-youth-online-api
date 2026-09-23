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

class StudentVerificationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Yêu cầu xác minh học sinh của bạn đã bị từ chối');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.student_verification_rejected',
            with: [
                'recipientName' => $this->recipient->name,
                'reason' => $this->reason,
                'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrlForEmail($this->recipient->email),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
