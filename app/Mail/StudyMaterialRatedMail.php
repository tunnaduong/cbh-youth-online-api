<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\NewsletterSubscriptionService;

class StudyMaterialRatedMail extends Mailable
{
  use Queueable, SerializesModels;

  public $material;
  public $rater;
  public $studyMaterialRating;
  public $recipient;
  public $unsubscribeUrl;

  /**
   * Create a new message instance.
   */
  public function __construct($material, $rater, $rating, $recipient = null)
  {
    $this->material = $material;
    $this->rater = $rater;
    $this->studyMaterialRating = $rating;
    $this->recipient = $recipient;
    $this->unsubscribeUrl = NewsletterSubscriptionService::unsubscribeUrl($recipient ?: $material->user);
  }

  /**
   * Get the message envelope.
   */
  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Tài liệu của bạn vừa nhận được đánh giá mới!',
    );
  }

  /**
   * Get the message content definition.
   */
  public function content(): Content
  {
    return new Content(
      markdown: 'emails.study_material_rated',
      with: [
        'materialTitle' => $this->material->title,
        'raterName' => $this->rater->profile->profile_name ?? $this->rater->username,
        'rating' => $this->studyMaterialRating->rating,
        'comment' => $this->studyMaterialRating->comment,
        'url' => env('APP_UI_URL', 'http://localhost:3000') . '/explore/study-materials/' . $this->material->id,
        'unsubscribeUrl' => $this->unsubscribeUrl,
      ],
    );
  }

  /**
   * Get the attachments for the message.
   *
   * @return array<int, \Illuminate\Mail\Mailables\Attachment>
   */
  public function attachments(): array
  {
    return [];
  }
}
