<?php

namespace App\Mail;

use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $date;
    public string $format;
    public string $scheduleLink;
    public string $discordLink;
    public string $deadline;
    public string $contactDeadline;
    public ?string $unsubscribeUrl;

    public function __construct(
        string $recipientName,
        string $date,
        string $format,
        string $scheduleLink,
        string $discordLink,
        string $deadline,
        string $contactDeadline,
        ?string $recipientEmail = null,
    ) {
        $this->recipientName = $recipientName;
        $this->date = $date;
        $this->format = $format;
        $this->scheduleLink = $scheduleLink;
        $this->discordLink = $discordLink;
        $this->deadline = $deadline;
        $this->contactDeadline = $contactDeadline;
        $this->unsubscribeUrl = NewsletterSubscriptionService::unsubscribeUrlForEmail($recipientEmail);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nhắc lịch phỏng vấn - CYO');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.interview_reminder',
            with: [
                'recipientName' => $this->recipientName,
                'date' => $this->date,
                'format' => $this->format,
                'scheduleLink' => $this->scheduleLink,
                'discordLink' => $this->discordLink,
                'deadline' => $this->deadline,
                'contactDeadline' => $this->contactDeadline,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
