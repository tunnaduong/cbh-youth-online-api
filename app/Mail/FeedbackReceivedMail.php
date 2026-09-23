<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an admin that a new bug report / suggestion came in from the in-app
 * feedback form.
 */
class FeedbackReceivedMail extends Mailable implements ShouldQueue
{
  use Queueable, SerializesModels;

  public const TYPE_LABELS = ['bug' => 'Báo lỗi', 'suggestion' => 'Góp ý', 'other' => 'Khác'];
  public const PLATFORM_LABELS = ['web' => 'Web', 'ios' => 'iOS', 'android' => 'Android'];

  public function __construct(public Feedback $feedback)
  {
  }

  public function envelope(): Envelope
  {
    $type = self::TYPE_LABELS[$this->feedback->type] ?? $this->feedback->type;
    $replyTo = $this->feedback->contact_email ?: $this->feedback->user?->email;

    return new Envelope(
      subject: "[{$type}] Góp ý mới #{$this->feedback->id} từ " . $this->senderName(),
      replyTo: $replyTo ? [new Address($replyTo)] : [],
    );
  }

  public function content(): Content
  {
    $f = $this->feedback;

    return new Content(
      markdown: 'emails.feedback_received',
      with: [
        'feedback' => $f,
        'typeLabel' => self::TYPE_LABELS[$f->type] ?? $f->type,
        'platformLabel' => (self::PLATFORM_LABELS[$f->platform] ?? $f->platform) . ($f->app_version ? " {$f->app_version}" : ''),
        'senderName' => $this->senderName(),
        'contactEmail' => $f->contact_email ?: $f->user?->email,
        'adminUrl' => 'https://www.chuyenbienhoa.com/admin/feedback',
      ],
    );
  }

  private function senderName(): string
  {
    return $this->feedback->user?->username ? '@' . $this->feedback->user->username : 'khách';
  }
}
