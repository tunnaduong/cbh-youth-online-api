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

/**
 * Tells an author a human reviewer turned their post/comment down.
 *
 * Only the admin reject flow uses this. An AI rejection never gets here:
 * that content is deleted outright and the author is told inline, in the
 * 422 response to the request that tried to post it.
 */
class ContentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthAccount $recipient,
        public string $contentType, // 'topic' | 'comment'
        public string $topicTitle,
        public string $reason = '',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->contentType === 'comment'
            ? 'Bình luận của bạn không được duyệt'
            : 'Bài viết của bạn không được duyệt';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.content_moderation_rejected',
            with: [
                'recipientName' => $this->recipient->username,
                'contentType' => $this->contentType,
                'topicTitle' => $this->topicTitle,
                'reason' => $this->reason,
                'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrl($this->recipient),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
