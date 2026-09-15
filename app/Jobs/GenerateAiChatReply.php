<?php

namespace App\Jobs;

use App\Events\AiTyping;
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
 * band, then creates and broadcasts Yoyo AI's reply message the same way a
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

    // Fire the AI's "typing" indicator now, since the Groq call below can
    // take several seconds - the AI has no client to whisper the way humans
    // do (see ChatProvider.js/ChatSocketContext.js), so this is a real
    // broadcast instead. Always stopped in `finally`, including on error,
    // so a failed job never leaves a stuck indicator.
    broadcast(new AiTyping($conversation->id, $aiAccount->id, $aiAccount->avatarUrl(), true));

    try {
      try {
        $result = $this->mode === 'summary'
          ? $this->runSummary($aiChatService, $conversation, $triggerMessage)
          : $this->runAsk($aiChatService, $triggerMessage);
      } catch (\Throwable $e) {
        Log::error('GenerateAiChatReply failed: ' . $e->getMessage());
        $result = ['content' => 'Xin lỗi, hiện tại AI đang gặp sự cố và không thể trả lời. Vui lòng thử lại sau.', 'reaction' => null];
      }

      $aiMessage = Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $aiAccount->id,
        'content' => $result['content'],
        'type' => 'text',
        'reply_to_message_id' => $triggerMessage->id,
      ]);

      $chatController = app(ChatController::class);
      $chatController->broadcastAiMessage($conversation, $aiMessage, $aiAccount);

      // Optional: the AI can also react to the original message it was called
      // on (its own choice, expressed via the "[REACT:type]" marker parsed out
      // in AiChatService) - never on its own canned fallback/error/help replies,
      // only when it actually got a real model response.
      if ($result['reaction']) {
        $chatController->reactAsAi($triggerMessage, $aiAccount, $result['reaction']);
      }
    } finally {
      broadcast(new AiTyping($conversation->id, $aiAccount->id, $aiAccount->avatarUrl(), false));
    }
  }

  /**
   * @return array{content: string, reaction: ?string}
   */
  private function runAsk(AiChatService $aiChatService, Message $triggerMessage): array
  {
    $repliedTo = $triggerMessage->replyTo;
    if ($repliedTo && $repliedTo->is_recalled) {
      return ['content' => 'Xin lỗi, tin nhắn bạn trả lời đã bị thu hồi nên Yoyo AI không thể đọc được nội dung đó nữa.', 'reaction' => null];
    }
    if ($repliedTo && $repliedTo->type !== 'text') {
      return ['content' => $this->unsupportedMediaReply($repliedTo->type), 'reaction' => null];
    }

    $conversation = $triggerMessage->conversation;
    $chainMessages = $this->collectReplyChainMessages($triggerMessage);

    // A private 1-on-1 with Yoyo AI is a real back-and-forth conversation,
    // not a series of one-off commands - every earlier turn is relevant
    // context whether or not the user bothered to reply/quote a specific
    // message, so pull in recent history automatically here instead of
    // requiring an explicit /summary first. Elsewhere (group chats, the
    // public room) context still only ever comes from an explicit reply
    // chain, same as before.
    $contextMessages = $conversation->isPrivateAiConversation()
      ? $this->collectRecentHistory($conversation, $triggerMessage)
          ->merge($chainMessages)
          ->unique('id')
          ->sortBy('created_at')
          ->values()
      : $chainMessages;

    $chain = $contextMessages->map(fn($m) => $this->toContext($m))->all();
    $question = $this->stripCommandPrefix($triggerMessage->content ?? '', '/ai');

    return $aiChatService->askAi(
      $chain,
      $question !== '' ? $question : ($triggerMessage->content ?? ''),
      $this->buildConversationInfo($conversation)
    );
  }

  /**
   * The last $limit messages in the conversation (oldest first), excluding
   * the trigger message itself and recalled messages - same window
   * /summary already uses (see runSummary), reused here so a private AI
   * chat gets that same context automatically on every turn.
   *
   * @return \Illuminate\Support\Collection<int, Message>
   */
  private function collectRecentHistory(Conversation $conversation, Message $excludeMessage, int $limit = 30): \Illuminate\Support\Collection
  {
    return $conversation->messages()
      ->whereNotNull('content')
      ->where('is_recalled', false)
      ->where('id', '!=', $excludeMessage->id)
      ->with('user.profile')
      ->orderBy('created_at', 'desc')
      ->limit($limit)
      ->get()
      ->reverse()
      ->values();
  }

  /**
   * Basic info about the chat itself (name, type, member list) so /ai and
   * /summary can answer questions like "who's in this group" or "what's
   * this group called" without needing to be told explicitly.
   */
  private function buildConversationInfo(Conversation $conversation): string
  {
    if ($conversation->is_public) {
      return "Thông tin cuộc trò chuyện hiện tại: đây là phòng chat chung công khai \"{$conversation->name}\" của toàn bộ cộng đồng CYO/CBH Youth Online - ai cũng có thể tham gia, danh sách thành viên rất lớn và luôn thay đổi nên không liệt kê đầy đủ ở đây.";
    }

    if ($conversation->type === 'private') {
      $otherParticipant = $conversation->participants()
        ->where('is_ai', false)
        ->with('profile')
        ->first();
      $otherName = $otherParticipant
        ? ($otherParticipant->profile->profile_name ?? $otherParticipant->username)
        : 'người dùng';

      return "Thông tin cuộc trò chuyện hiện tại: đây là đoạn chat riêng (1-1) giữa bạn (Yoyo AI) và {$otherName}.";
    }

    // Group chat: name + member list (name, username, role).
    $members = $conversation->participants()
      ->where('is_ai', false)
      ->with('profile')
      ->get()
      ->map(function ($member) {
        $name = $member->profile->profile_name ?? $member->username;
        $role = $member->pivot->role ?? 'member';
        $roleLabel = match ($role) {
          'owner' => 'trưởng nhóm',
          'deputy' => 'phó nhóm',
          default => 'thành viên',
        };
        return "{$name} (@{$member->username}, {$roleLabel})";
      })
      ->implode(', ');

    $groupName = $conversation->name ?: 'Nhóm chưa đặt tên';

    return "Thông tin cuộc trò chuyện hiện tại: đây là nhóm chat tên \"{$groupName}\", gồm các thành viên: {$members}.";
  }

  /**
   * Yoyo AI can only read text today - if the message a user replied to (with
   * /ai, or by continuing a conversation with the AI) is a photo/video/file,
   * say so plainly instead of silently ignoring the attachment or hallucinating
   * about content it never actually saw.
   */
  private function unsupportedMediaReply(string $mediaType): string
  {
    $label = match ($mediaType) {
      'image' => 'hình ảnh',
      'video' => 'video',
      'file' => 'tệp đính kèm',
      default => 'nội dung này',
    };

    return "Xin lỗi, hiện tại Yoyo AI chưa thể đọc và xử lý {$label}, chỉ có thể đọc tin nhắn văn bản. Vui lòng thử lại với một tin nhắn chữ nhé.";
  }

  /**
   * @return array{content: string, reaction: ?string}
   */
  private function runSummary(AiChatService $aiChatService, Conversation $conversation, Message $triggerMessage): array
  {
    // /summary as a reply (e.g. replying to a specific message and asking to
    // summarize from there) only makes sense against text - same rule as /ai.
    $repliedTo = $triggerMessage->replyTo;
    if ($repliedTo && $repliedTo->is_recalled) {
      return ['content' => 'Xin lỗi, tin nhắn bạn trả lời đã bị thu hồi nên Yoyo AI không thể đọc được nội dung đó nữa.', 'reaction' => null];
    }
    if ($repliedTo && $repliedTo->type !== 'text') {
      return ['content' => $this->unsupportedMediaReply($repliedTo->type), 'reaction' => null];
    }

    $customRequest = $this->stripCommandPrefix($triggerMessage->content ?? '', '/summary');

    $recentQuery = $conversation->messages()
      ->whereNotNull('content')
      ->where('is_recalled', false)
      ->with('user.profile');

    // "/summary" as a reply anchors the window to end at the replied-to
    // message instead of "now" - so users can jump back and summarize an
    // older stretch of the conversation instead of always getting the very
    // latest messages.
    if ($repliedTo) {
      $recentQuery->where('created_at', '<=', $repliedTo->created_at);
    }

    $recent = $recentQuery
      ->orderBy('created_at', 'desc')
      ->limit(50)
      ->get()
      ->reverse()
      ->values();

    // Exclude the trigger message itself (the "/summary ..." command) from
    // the transcript being summarized - it's an instruction, not content.
    $recent = $recent->reject(fn($m) => $m->id === $triggerMessage->id)->values();

    $trimmed = $aiChatService->trimForSummary($recent);
    $context = $trimmed->map(fn($m) => $this->toContext($m))->all();

    return $aiChatService->summarizeAi(
      $context,
      $customRequest !== '' ? $customRequest : null,
      $this->buildConversationInfo($conversation)
    );
  }

  /**
   * Walk the reply_to_message_id chain (nested replies included) up to a
   * sane depth, oldest first, so the AI sees the full quoted conversation
   * a user built up without needing every message re-sent to it manually.
   *
   * @return \Illuminate\Support\Collection<int, Message>
   */
  private function collectReplyChainMessages(Message $message, int $maxDepth = 20): \Illuminate\Support\Collection
  {
    $chain = collect();
    $current = $message->replyTo()->with('user.profile')->first();
    $depth = 0;

    while ($current && $depth < $maxDepth) {
      $chain->prepend($current);
      $current = $current->replyTo()->with('user.profile')->first();
      $depth++;
    }

    return $chain->values();
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
      // A recalled/unsent message's content is never actually deleted from
      // the DB (recall is a display-only flag, same as getMessages() hiding
      // it) - without this check it would otherwise leak straight into the
      // AI's context even though every other part of the app already treats
      // it as gone. Same reasoning as the media placeholder below: labeled,
      // not silently included or skipped, so the AI knows something was
      // there but can't see what. Only relevant for older messages further
      // back in a reply chain/history - the immediate reply target is
      // checked and refused outright in runAsk()/runSummary() instead.
      'content' => $message->is_recalled
        ? '[tin nhắn đã bị thu hồi]'
        : ($message->type === 'text'
          ? (string) $message->content
          : '[' . match ($message->type) {
            'image' => 'đã gửi một hình ảnh',
            'video' => 'đã gửi một video',
            'file' => 'đã gửi một tệp đính kèm',
            default => 'nội dung không phải văn bản',
          } . ']'),
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
