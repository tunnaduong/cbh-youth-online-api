<?php

namespace App\Mail;

use App\Models\AuthAccount;
use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResultNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $status;
    public string $subjectLine;
    public ?string $unsubscribeUrl;

    public function __construct(
        string $recipientName,
        string $status,
        ?string $recipientEmail = null,
    ) {
        $this->recipientName = $recipientName;
        $this->status = $status;
        $this->subjectLine = $status === 'accepted'
            ? 'Bạn đã chính thức trở thành thành viên GEN 1.0 của CYO'
            : 'Bạn đã không trở thành thành viên GEN 1.0 của CYO';
        $this->unsubscribeUrl = NewsletterSubscriptionService::unsubscribeUrlForEmail($recipientEmail);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.result_notification',
            with: [
                'recipientName' => $this->recipientName,
                'status' => $this->status,
                'subjectLine' => $this->subjectLine,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
