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

class ContentApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthAccount $recipient,
        public string $contentType, // 'topic' | 'comment'
        public string $topicTitle,
        public string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->contentType === 'comment'
            ? 'Bình luận của bạn đã được kiểm duyệt thành công'
            : 'Bài viết của bạn đã được kiểm duyệt thành công';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.content_moderation_approved',
            with: [
                'recipientName' => $this->recipient->username,
                'contentType' => $this->contentType,
                'topicTitle' => $this->topicTitle,
                'url' => $this->url,
                'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrl($this->recipient),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
