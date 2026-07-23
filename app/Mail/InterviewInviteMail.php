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

class InterviewInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $teamName;
    public string $date;
    public string $format;
    public string $deadline;
    public ?string $meetingLink;
    public ?string $unsubscribeUrl;

    public function __construct(
        string $recipientName,
        string $teamName,
        string $date,
        string $format,
        string $deadline,
        ?string $meetingLink = null,
        ?string $recipientEmail = null,
    ) {
        $this->recipientName = $recipientName;
        $this->teamName = $teamName;
        $this->date = $date;
        $this->format = $format;
        $this->deadline = $deadline;
        $this->meetingLink = $meetingLink;
        $this->unsubscribeUrl = NewsletterSubscriptionService::unsubscribeUrlForEmail($recipientEmail);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thư mời phỏng vấn - CYO');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.interview_invite',
            with: [
                'recipientName' => $this->recipientName,
                'date' => $this->date,
                'format' => $this->format,
                'deadline' => $this->deadline,
                'meetingLink' => $this->meetingLink,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
