<?php

namespace App\Jobs;

use App\Http\Controllers\ChatController;
use App\Models\AuthAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the Groq call for a /ai, /summary, or reply-to-AI trigger out of
 * band, then creates and broadcasts CYO AI's reply message the same way a
 * human-sent message would be (see ChatController::finalizeAndBroadcastMessage).
 */
class GenerateAiChatReply implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $timeout = 90;
  public int $tries = 1;

  /**
   * @param  int  $conversationId
   * @param  int  $triggerMessageId  The user message that triggered this (used as reply_to for the AI's answer).
   * @param  string  $mode  'ai' | 'summary'
   */
  public function __construct(
    private int $conversationId,
    private int $triggerMessageId,
    private string $mode,
  ) {}

  public function handle(AiChatService $aiChatService): void
  {
    $conversation = Conversation::find($this->conversationId);
    $triggerMessage = Message::find($this->triggerMessageId);
    $aiAccount = AuthAccount::where('is_ai', true)->first();

    if (!$conversation || !$triggerMessage || !$aiAccount) {
      Log::warning('GenerateAiChatReply: missing conversation/message/AI account, skipping.');
      return;
    }

    try {
      $reply = $this->mode === 'summary'
        ? $this->runSummary($aiChatService, $conversation, $triggerMessage)
        : $this->runAsk($aiChatService, $triggerMessage);
    } catch (\Throwable $e) {
      Log::error('GenerateAiChatReply failed: ' . $e->getMessage());
      $reply = 'Xin lỗi, hiện tại AI đang gặp sự cố và không thể trả lời. Vui lòng thử lại sau.';
    }

    $aiMessage = Message::create([
      'conversation_id' => $conversation->id,
      'user_id' => $aiAccount->id,
      'content' => $reply,
      'type' => 'text',
      'reply_to_message_id' => $triggerMessage->id,
    ]);

    app(ChatController::class)->broadcastAiMessage($conversation, $aiMessage, $aiAccount);
  }

  private function runAsk(AiChatService $aiChatService, Message $triggerMessage): string
  {
    $chain = $this->collectReplyChain($triggerMessage);
    $question = $this->stripCommandPrefix($triggerMessage->content ?? '', '/ai');

    return $aiChatService->askAi($chain, $question !== '' ? $question : ($triggerMessage->content ?? ''));
  }

  private function runSummary(AiChatService $aiChatService, Conversation $conversation, Message $triggerMessage): string
  {
    $customRequest = $this->stripCommandPrefix($triggerMessage->content ?? '', '/summary');

    $recent = $conversation->messages()
      ->whereNotNull('content')
      ->where('is_recalled', false)
      ->orderBy('created_at', 'desc')
      ->limit(50)
      ->with('user.profile')
      ->get()
      ->reverse()
      ->values();

    // Exclude the trigger message itself (the "/summary ..." command) from
    // the transcript being summarized - it's an instruction, not content.
    $recent = $recent->reject(fn($m) => $m->id === $triggerMessage->id)->values();

    $trimmed = $aiChatService->trimForSummary($recent);
    $context = $trimmed->map(fn($m) => $this->toContext($m))->all();

    return $aiChatService->summarizeAi($context, $customRequest !== '' ? $customRequest : null);
  }

  /**
   * Walk the reply_to_message_id chain (nested replies included) up to a
   * sane depth, oldest first, so the AI sees the full quoted conversation
   * a user built up without needing every message re-sent to it manually.
   *
   * @return array<int, array{role: string, name: ?string, content: string}>
   */
  private function collectReplyChain(Message $message, int $maxDepth = 20): array
  {
    $chain = [];
    $current = $message->replyTo()->with('user.profile')->first();
    $depth = 0;

    while ($current && $depth < $maxDepth) {
      array_unshift($chain, $this->toContext($current));
      $current = $current->replyTo()->with('user.profile')->first();
      $depth++;
    }

    return $chain;
  }

  /**
   * @return array{role: string, name: ?string, content: string}
   */
  private function toContext(Message $message): array
  {
    $isAi = (bool) optional($message->user)->is_ai;
    $name = $message->guest_name
      ?? optional(optional($message->user)->profile)->profile_name
      ?? optional($message->user)->username
      ?? 'Người dùng';

    return [
      'role' => $isAi ? 'assistant' : 'user',
      'name' => $isAi ? null : $name,
      'content' => (string) $message->content,
    ];
  }

  private function stripCommandPrefix(string $content, string $command): string
  {
    $trimmed = trim($content);
    if (stripos($trimmed, $command) === 0) {
      return trim(substr($trimmed, strlen($command)));
    }

    return $trimmed;
  }
}
