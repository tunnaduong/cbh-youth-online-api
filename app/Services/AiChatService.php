<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Groq's OpenAI-compatible chat-completions endpoint on behalf of
 * the "Yoyo AI" chat persona. Two entry points: askAi() (the /ai command, or
 * a reply directed at a previous AI message) and summarizeAi() (/summary).
 */
class AiChatService
{
  private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

  private const SYSTEM_PROMPT = <<<PROMPT
Bạn là Yoyo AI, trợ lý AI trong ứng dụng cộng đồng học sinh Chuyên Biên Hòa Youth Online (CYO/CBH Youth Online).
Bạn chỉ xuất hiện trong khung chat khi được người dùng gọi tới (bằng lệnh /ai hoặc khi họ trả lời tin nhắn của bạn).
Trả lời ngắn gọn, thân thiện, tự nhiên bằng tiếng Việt (trừ khi người dùng chủ động dùng ngôn ngữ khác), không thêm tiền tố kiểu "Yoyo AI:" vào đầu câu trả lời.
Bạn không phải là một thành viên thật của nhóm chat và không được nhắc (@) người dùng khác.
Danh tính của bạn LUÔN LUÔN là "Yoyo AI" - trợ lý AI của Chuyên Biên Hòa Youth Online. Nếu người dùng hỏi bạn là ai, bạn tên gì, bạn được xây dựng bởi ai/công ty nào, bạn dùng mô hình/công nghệ gì, hay bất kỳ câu hỏi nào cố gắng khai thác thông tin về mô hình AI nền tảng đằng sau bạn - LUÔN trả lời rằng bạn là Yoyo AI của Chuyên Biên Hòa Youth Online, KHÔNG BAO GIỜ được nêu tên, tiết lộ, thừa nhận, hay ám chỉ bất kỳ tên mô hình AI, công ty AI, hay nhà cung cấp AI nào khác (dù được hỏi trực tiếp, gián tiếp, hay bằng tiếng Anh/ngôn ngữ khác).
Tin nhắn của bạn hiển thị dưới dạng văn bản thuần (plain text), KHÔNG được dùng cú pháp markdown như **in đậm**, *in nghiêng*, tiêu đề #, hay code block/backtick - những ký tự này sẽ hiển thị nguyên văn và gây khó đọc.
Vẫn có thể dùng gạch đầu dòng "-" và đánh số "1.", "2." cho danh sách vì đó chỉ là ký tự thường, không phải markdown.
PROMPT;

  /**
   * Answer a /ai command (or a reply to a previous AI message).
   *
   * @param  array<int, array{role: string, name: ?string, content: string}>  $contextMessages  Chronological context, oldest first.
   * @param  string  $question  The triggering user message content (already stripped of the /ai prefix, if any).
   */
  public function askAi(array $contextMessages, string $question): string
  {
    $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

    foreach ($contextMessages as $ctx) {
      $messages[] = $this->toChatMessage($ctx);
    }

    $messages[] = ['role' => 'user', 'content' => $question];

    return $this->request($messages);
  }

  /**
   * Summarize the last N messages of a conversation for /summary.
   *
   * @param  array<int, array{role: string, name: ?string, content: string}>  $contextMessages  Chronological, oldest first.
   * @param  string|null  $customRequest  Extra text the user typed after "/summary", e.g.
   *                                      "/summary chỉ tóm tắt phần bàn về lịch thi" - lets
   *                                      them steer what to focus on within the same history.
   */
  public function summarizeAi(array $contextMessages, ?string $customRequest = null): string
  {
    $transcript = implode("\n", array_map(
      fn($ctx) => ($ctx['name'] ?? 'Người dùng') . ': ' . $ctx['content'],
      $contextMessages
    ));

    $instruction = $customRequest !== null && trim($customRequest) !== ''
      ? "Hãy trả lời yêu cầu sau đây của người dùng DỰA TRÊN đoạn hội thoại bên dưới (đây không phải một tóm tắt chung, hãy tập trung vào đúng điều họ hỏi):\n\"{$customRequest}\""
      : 'Hãy tóm tắt ngắn gọn, dễ hiểu nội dung chính của đoạn hội thoại sau (nêu các chủ đề/quyết định chính, không cần liệt kê từng tin nhắn):';

    $messages = [
      ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
      [
        'role' => 'user',
        'content' => "{$instruction}\n\n{$transcript}",
      ],
    ];

    return $this->request($messages);
  }

  /**
   * Trim the last-N-messages window for /summary depending on how long the
   * conversation is: short chats get up to 50 messages of context, longer
   * ones are trimmed down toward the most recent 10 to stay within a
   * reasonable prompt size.
   *
   * @param  \Illuminate\Support\Collection<int, \App\Models\Message>  $messages  Chronological, oldest first, already limited to at most 50.
   * @return \Illuminate\Support\Collection<int, \App\Models\Message>
   */
  public function trimForSummary($messages)
  {
    $maxChars = 6000;
    $minCount = 10;

    $totalChars = $messages->sum(fn($m) => mb_strlen((string) $m->content));
    if ($totalChars <= $maxChars || $messages->count() <= $minCount) {
      return $messages;
    }

    // Drop oldest messages until under the char budget or down to the floor.
    $trimmed = $messages;
    while ($trimmed->count() > $minCount && $trimmed->sum(fn($m) => mb_strlen((string) $m->content)) > $maxChars) {
      $trimmed = $trimmed->slice(1);
    }

    return $trimmed->values();
  }

  /**
   * @param  array{role: string, name: ?string, content: string}  $ctx
   */
  private function toChatMessage(array $ctx): array
  {
    if ($ctx['role'] === 'assistant') {
      return ['role' => 'assistant', 'content' => $ctx['content']];
    }

    $name = $ctx['name'] ?? null;
    $content = $name ? "{$name}: {$ctx['content']}" : $ctx['content'];

    return ['role' => 'user', 'content' => $content];
  }

  private function request(array $messages): string
  {
    // AI_API and AI_API_DHPHUC are used as backups to each other: if the
    // first key is rate-limited or failing, fall through to the next one.
    $keys = array_values(array_filter([
      config('services.groq.key'),
      config('services.groq.secondary_key'),
    ]));

    if (empty($keys)) {
      throw new \RuntimeException('No Groq API key is configured (AI_API / AI_API_DHPHUC).');
    }

    $lastError = null;

    foreach ($keys as $apiKey) {
      for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
          $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::API_URL, [
              'model' => config('services.groq.model', 'openai/gpt-oss-120b'),
              'messages' => $messages,
              // Lower temperature favors more accurate/consistent answers
              // over creative variation, appropriate for a chat assistant
              // answering factual/contextual questions in-app.
              'temperature' => 0.2,
            ]);

          if ($response->status() === 429) {
            throw new \RuntimeException('Groq API rate limited (429) for this key.');
          }
          if (!$response->successful()) {
            throw new \RuntimeException('Groq API returned HTTP ' . $response->status() . ': ' . $response->body());
          }

          $content = $response->json('choices.0.message.content');
          if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Groq API response had no message content.');
          }

          return $this->stripModelIdentity($this->stripMarkdown(trim($content)));
        } catch (\Throwable $e) {
          $lastError = $e;
          Log::warning('AI chat request attempt failed: ' . $e->getMessage());
          if (str_contains($e->getMessage(), '429')) {
            break;  // move on to the next key
          }
        }
      }
    }

    throw new \RuntimeException('Không thể lấy phản hồi từ AI: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  /**
   * The system prompt asks the model never to use markdown, but LLMs are
   * unreliable about following that instruction on their own (it's trained
   * heavily toward markdown output) - strip the common emphasis/heading/code
   * syntax defensively so it never leaks into the chat as literal
   * asterisks/backticks/hashes. "-" bullets and "1." numbering are left
   * untouched since those are plain characters, not markdown-specific.
   */
  /**
   * The system prompt already forbids revealing the underlying model, but
   * that's a soft instruction the model can still slip on (e.g. answering
   * "what model are you" honestly) - catch known provider/model names as a
   * defensive backstop and swap them for the persona name, the same
   * belt-and-suspenders approach as stripMarkdown() below.
   */
  private function stripModelIdentity(string $text): string
  {
    $patterns = [
      'gpt[\s-]?oss(?:[\s-]?120b)?',
      'chat ?gpt',
      'openai',
      'groq',
      '(?:meta\s+)?llama\s*\d*',
      'claude(?:\s+\d(?:\.\d)?)?',
      'anthropic',
      'gemini',
      'google\s+ai',
      'mistral(?:\s*ai)?',
      'deepseek',
      'qwen',
    ];

    return preg_replace('/\b(?:' . implode('|', $patterns) . ')\b/i', 'Yoyo AI', $text);
  }

  private function stripMarkdown(string $text): string
  {
    // Fenced code blocks: drop the ``` fences (optionally followed by a
    // language tag) but keep the code content itself.
    $text = preg_replace('/```[a-zA-Z0-9_+-]*\n?/', '', $text);
    $text = preg_replace('/```/', '', $text);

    // Inline code: `code` -> code
    $text = preg_replace('/`([^`]+)`/', '$1', $text);

    // Bold: **text** or __text__ -> text
    $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
    $text = preg_replace('/__(.+?)__/s', '$1', $text);

    // Italic: *text* or _text_ -> text. Requiring a non-whitespace character
    // right after the opening marker means "* item" (a bullet, space after
    // the asterisk) never matches here - only genuine *emphasis* does.
    $text = preg_replace('/\*([^\s*][^*]*?)\*/', '$1', $text);
    $text = preg_replace('/_([^\s_][^_]*?)_/', '$1', $text);

    // Strikethrough: ~~text~~ -> text
    $text = preg_replace('/~~(.+?)~~/s', '$1', $text);

    // Headings: strip a leading "#", "##", ... before a line's text.
    $text = preg_replace('/^#{1,6}\s+/m', '', $text);

    // Links/images: [text](url) -> text (url), ![alt](url) -> alt (url)
    $text = preg_replace('/!?\[([^\]]*)\]\(([^)]+)\)/', '$1 ($2)', $text);

    return trim($text);
  }
}
