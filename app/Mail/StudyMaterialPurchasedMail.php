<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudyMaterialPurchasedMail extends Mailable
{
  use Queueable, SerializesModels;

  public $material;
  public $buyer;

  /**
   * Create a new message instance.
   */
  public function __construct($material, $buyer)
  {
    $this->material = $material;
    $this->buyer = $buyer;
  }

  /**
   * Get the message envelope.
   */
  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Tài liệu của bạn vừa được mua!',
    );
  }

  /**
   * Get the message content definition.
   */
  public function content(): Content
  {
    return new Content(
      markdown: 'emails.study_material_purchased',
      with: [
        'materialTitle' => $this->material->title,
        'buyerName' => $this->buyer->profile->profile_name ?? $this->buyer->username,
        'price' => $this->material->price,
        'url' => env('APP_UI_URL', 'http://localhost:3000') . '/explore/study-materials/' . $this->material->id,
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
