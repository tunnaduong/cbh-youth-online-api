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
 * Tells an author their post/comment is held in the moderation queue.
 *
 * Counterpart of ContentApprovedMail: this one goes out the moment the AI
 * routes something to a human, so the author isn't left wondering why their
 * post vanished from the feed.
 */
class ContentPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthAccount $recipient,
        public string $contentType, // 'topic' | 'comment'
        public string $topicTitle,
        public string $url,
        public string $reason = '',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->contentType === 'comment'
            ? 'Bình luận của bạn đang chờ kiểm duyệt'
            : 'Bài viết của bạn đang chờ kiểm duyệt';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.content_moderation_pending',
            with: [
                'recipientName' => $this->recipient->username,
                'contentType' => $this->contentType,
                'topicTitle' => $this->topicTitle,
                'url' => $this->url,
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
