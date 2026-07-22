<?php

namespace App\Mail;

use App\Models\Notification;
use App\Services\NewsletterSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForumInteractionMail extends Mailable implements ShouldQueue
{
  use Queueable, SerializesModels;

  public function __construct(
    public Notification $notification,
    public $recipient,
  ) {
    // Ensure relations are available when serialized/unserialized by the queue worker
    $this->notification->loadMissing(['actor.profile']);
  }

  public function envelope(): Envelope
  {
    return new Envelope(subject: $this->resolveSubject());
  }

  public function content(): Content
  {
    $baseUrl = rtrim(config('app.ui_url', env('APP_UI_URL', 'http://localhost:3000')), '/');
    $path = $this->notification->data['url'] ?? '/';

    return new Content(
      markdown: 'emails.forum_interaction',
      with: [
        'title' => $this->resolveSubject(),
        'message' => $this->message(),
        'url' => $baseUrl . $path,
        'unsubscribeUrl' => NewsletterSubscriptionService::unsubscribeUrl($this->recipient),
      ],
    );
  }

  public function attachments(): array
  {
    return [];
  }

  private function resolveSubject(): string
  {
    return match ($this->notification->type) {
      'topic_liked', 'topic_downvoted' => 'Bài viết của bạn vừa nhận được lượt vote mới',
      'comment_liked', 'comment_downvoted' => 'Bình luận của bạn vừa nhận được lượt vote mới',
      'topic_commented', 'comment_replied' => 'Bài viết của bạn vừa có bình luận mới',
      'followed' => 'Bạn vừa có người theo dõi mới',
      'direct_message' => 'Bạn vừa nhận được tin nhắn mới',
      'mentioned' => 'Bạn vừa được nhắc đến trên diễn đàn',
      default => 'Bạn có thông báo mới trên diễn đàn',
    };
  }

  private function message(): string
  {
    $data = $this->notification->data ?? [];
    $actorName = $this->notification->actor?->profile?->profile_name
      ?: ($this->notification->actor?->username ?: 'Một người dùng');

    return match ($this->notification->type) {
      'topic_liked' => "{$actorName} đã upvote bài viết \"{$data['topic_title']}\" của bạn.",
      'topic_downvoted' => "{$actorName} đã downvote bài viết \"{$data['topic_title']}\" của bạn.",
      'comment_liked' => "{$actorName} đã upvote bình luận của bạn trong bài viết \"{$data['topic_title']}\".",
      'comment_downvoted' => "{$actorName} đã downvote bình luận của bạn trong bài viết \"{$data['topic_title']}\".",
      'topic_commented' => "{$actorName} đã bình luận trong bài viết \"{$data['topic_title']}\" của bạn.",
      'comment_replied' => "{$actorName} đã trả lời bình luận của bạn trong bài viết \"{$data['topic_title']}\".",
      'followed' => "{$actorName} đã bắt đầu theo dõi bạn.",
      'direct_message' => "{$actorName} đã gửi cho bạn một tin nhắn mới.",
      'mentioned' => "{$actorName} đã nhắc đến bạn trong bài viết hoặc bình luận.",
      default => 'Bạn có một tương tác mới trên diễn đàn.',
    };
  }
}