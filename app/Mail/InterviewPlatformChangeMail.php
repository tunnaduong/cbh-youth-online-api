<?php

namespace App\Mail;

use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewPlatformChangeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $date;
    public string $scheduleLink;
    public ?string $unsubscribeUrl;

    public function __construct(
        string $recipientName,
        string $date,
        string $scheduleLink,
        ?string $recipientEmail = null,
    ) {
        $this->recipientName = $recipientName;
        $this->date = $date;
        $this->scheduleLink = $scheduleLink;
        $this->unsubscribeUrl = NewsletterSubscriptionService::unsubscribeUrlForEmail($recipientEmail);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thay đổi hình thức phỏng vấn - CYO');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.interview_platform_change',
            with: [
                'recipientName' => $this->recipientName,
                'date' => $this->date,
                'scheduleLink' => $this->scheduleLink,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
