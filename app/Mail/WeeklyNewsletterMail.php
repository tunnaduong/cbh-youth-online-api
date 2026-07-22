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
        $baseUrl = rtrim(config('app.ui_url', env('APP_UI_URL', 'http://localhost:3000')), '/');

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