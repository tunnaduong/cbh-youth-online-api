<?php

namespace App\Mail;

use App\Models\AuthAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Alerts an admin that a topic/comment needs a human moderation review.
 *
 * Counterpart of ContentPendingMail (which tells the author their content is
 * held): this one tells the reviewer there's something to look at.
 */
class AdminModerationPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthAccount $recipient,
        public string $contentType, // 'topic' | 'comment'
        public ?string $authorUsername,
        public string $reason,
        public string $queueUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->contentType === 'comment'
            ? 'Có bình luận mới cần kiểm duyệt'
            : 'Có bài viết mới cần kiểm duyệt';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin_moderation_pending',
            with: [
                'recipientName' => $this->recipient->username,
                'contentType' => $this->contentType,
                'authorUsername' => $this->authorUsername,
                'reason' => $this->reason,
                'queueUrl' => $this->queueUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
