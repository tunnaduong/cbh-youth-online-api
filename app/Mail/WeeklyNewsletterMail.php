<?php

namespace App\Mail;

use App\Models\AuthAccount;
use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyNewsletterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthAccount $recipient,
        public array $topics,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bản tin diễn đàn tuần này');
    }

    public function content(): Content
    {
        // Fallback must be the public site: a production .env without APP_UI_URL
        // would otherwise put localhost links in every newsletter.
        $baseUrl = rtrim(env('APP_UI_URL', 'https://chuyenbienhoa.com'), '/');

        return new Content(
            markdown: 'emails.weekly_newsletter',
            with: [
                'baseUrl' => $baseUrl,
                'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrl($this->recipient),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}