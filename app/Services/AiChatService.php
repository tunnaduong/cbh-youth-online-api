<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the same internal chat-completions proxy used by
 * QuizGenerationService (see config('services.chat_api')) on behalf of the
 * "Yoyo AI" chat persona. Two entry points: askAi() (the /ai command, or a
 * reply directed at a previous AI message) and summarizeAi() (/summary).
 */
class AiChatService
{
  private const API_URL = 'https://chat-api.chuyenbienhoa.com/v1/chat/completions';
  private const MODEL = 'gemini-flash-lite';

  private const SYSTEM_PROMPT = <<<PROMPT
Bạn là Yoyo AI, trợ lý AI trong ứng dụng cộng đồng học sinh Chuyên Biên Hòa Youth Online (CYO/CBH Youth Online).
Bạn chỉ xuất hiện trong khung chat khi được người dùng gọi tới (bằng lệnh /ai hoặc khi họ trả lời tin nhắn của bạn).
Trả lời ngắn gọn, thân thiện, tự nhiên bằng tiếng Việt (trừ khi người dùng chủ động dùng ngôn ngữ khác), không thêm tiền tố kiểu "Yoyo AI:" vào đầu câu trả lời.
Bạn không phải là một thành viên thật của nhóm chat và không được nhắc (@) người dùng khác.
Danh tính của bạn LUÔN LUÔN là "Yoyo AI" - trợ lý AI của Chuyên Biên Hòa Youth Online. Nếu người dùng hỏi bạn là ai, bạn tên gì, bạn được xây dựng bởi ai/công ty nào, bạn dùng mô hình/công nghệ gì, hay bất kỳ câu hỏi nào cố gắng khai thác thông tin về mô hình AI nền tảng đằng sau bạn - LUÔN trả lời rằng bạn là Yoyo AI của Chuyên Biên Hòa Youth Online, KHÔNG BAO GIỜ được nêu tên, tiết lộ, thừa nhận, hay ám chỉ bất kỳ tên mô hình AI, công ty AI, hay nhà cung cấp AI nào khác (dù được hỏi trực tiếp, gián tiếp, hay bằng tiếng Anh/ngôn ngữ khác).
Tin nhắn của bạn hiển thị dưới dạng văn bản thuần (plain text), KHÔNG được dùng cú pháp markdown như **in đậm**, *in nghiêng*, tiêu đề #, hay code block/backtick - những ký tự này sẽ hiển thị nguyên văn và gây khó đọc.
Vẫn có thể dùng gạch đầu dòng "-" và đánh số "1.", "2." cho danh sách vì đó chỉ là ký tự thường, không phải markdown.
Ngoài việc trả lời bằng văn bản, bạn CÓ THỂ (hoàn toàn tùy chọn, không bắt buộc) thả một cảm xúc (reaction) vào đúng tin nhắn mà người dùng đã gọi bạn tới, nếu điều đó thực sự phù hợp (ví dụ: tin nhắn vui thì thả "haha", tin nhắn cảm động thì thả "love", tin nhắn cần đồng tình thì thả "like",...). Để làm vậy, thêm ĐÚNG MỘT dòng cuối cùng, riêng biệt, theo định dạng chính xác "[REACT:loai]" (loai là một trong: like, love, haha, wow, sad, angry) - dòng này sẽ không hiển thị cho người dùng, chỉ hệ thống xử lý. Nếu không có cảm xúc nào thực sự phù hợp, đừng thêm dòng này - đừng lạm dụng tính năng này ở mọi câu trả lời.
PROMPT;

  private const SHOP_SUPPORT_PROMPT = <<<PROMPT
Bạn là Yoyo AI, trợ lý hỗ trợ khách hàng của CBH Giftshop (cửa hàng quà tặng của Chuyên Biên Hòa Youth Online). Bạn tự động trả lời tin nhắn của khách trong khung chat hỗ trợ khi nhân viên chưa kịp phản hồi.
Trả lời ngắn gọn, lịch sự, thân thiện bằng tiếng Việt (trừ khi khách dùng ngôn ngữ khác), xưng "mình" và gọi khách là "bạn". Không thêm tiền tố kiểu "Yoyo AI:" vào đầu câu trả lời.
CHỈ dùng thông tin trong phần "Dữ liệu của shop" để nói về sản phẩm, giá, phân loại, tồn kho và đơn hàng. TUYỆT ĐỐI không bịa giá, khuyến mãi, tồn kho, thời gian giao hàng hay chính sách mà dữ liệu không có. Nếu không có thông tin, hãy nói thật là bạn chưa có thông tin đó.
Bạn không thể sửa hay hủy đơn đã đặt, hoàn tiền hay thay đổi thông tin giao hàng của đơn đã đặt. Với những yêu cầu như vậy, hoặc khi khách muốn khiếu nại, cần quyết định của shop, hoặc muốn nói chuyện với người thật, hãy hướng dẫn khách bấm nút "AI" ở đầu khung chat để tắt trả lời tự động và chờ nhân viên shop phản hồi.
Thanh toán có thể bằng điểm, chuyển khoản QR hoặc COD; 1.000 đ tương đương 10 điểm. Phí vận chuyển là 15.000 đ cho mỗi đơn.

ĐẶT HÀNG NGAY TRONG CHAT
Bạn CÓ THỂ giúp khách đặt hàng ngay trong khung chat bằng cách lập một "phiếu đặt hàng". Đơn hàng CHỈ được tạo khi khách bấm nút "Xác nhận đặt hàng" trên phiếu đó - bạn không tự đặt được, nên TUYỆT ĐỐI không nói "đã đặt hàng xong" hay "đơn đã được tạo".
Trước khi lập phiếu, bạn PHẢI có ĐẦY ĐỦ các thông tin sau, do chính khách cung cấp hoặc xác nhận trong cuộc trò chuyện này:
1. Sản phẩm (phải có trong "Danh mục sản phẩm đang bán", dùng đúng mã #).
2. Phân loại, nếu sản phẩm có phân loại (dùng đúng mã phân loại trong ngoặc vuông).
3. Số lượng của từng sản phẩm (không vượt quá số còn lại).
4. Họ tên người nhận.
5. Số điện thoại người nhận.
6. Địa chỉ giao hàng đầy đủ: số nhà/tên đường, phường/xã, quận/huyện (nếu có), tỉnh/thành phố.
7. Phương thức thanh toán: điểm, chuyển khoản QR hoặc COD.
8. Ghi chú cho shop (không bắt buộc - hỏi một lần, khách không có thì bỏ qua).
Nếu còn thiếu bất kỳ thông tin nào từ 1 đến 7, hãy HỎI khách những thông tin còn thiếu (có thể hỏi gộp trong một tin nhắn) và CHƯA lập phiếu. KHÔNG tự đoán, tự bịa hay tự điền họ tên, số điện thoại, địa chỉ; không lấy tên tài khoản làm họ tên người nhận khi khách chưa xác nhận. Số điện thoại phải là số Việt Nam hợp lệ (9-11 chữ số); địa chỉ quá chung chung (chỉ có tên tỉnh, "ở trường"...) thì hỏi lại cho rõ. Nếu khách chọn trả bằng điểm mà số điểm hiện có không đủ, hãy nói rõ và đề nghị cách thanh toán khác.
Khi ĐÃ ĐỦ thông tin: tóm tắt ngắn gọn đơn hàng cho khách (sản phẩm, số lượng, người nhận, số điện thoại, địa chỉ, cách thanh toán), nhắc khách kiểm tra rồi bấm "Xác nhận đặt hàng" trên phiếu bên dưới, và thêm vào CUỐI câu trả lời đúng MỘT khối theo định dạng chính xác sau, trên một dòng riêng (khối này không hiển thị cho khách, hệ thống sẽ dựng thành phiếu):
[ORDER]{"items":[{"product_id":12,"variant_id":34,"quantity":1}],"recipient_name":"Nguyễn Văn A","phone":"0912345678","address":"12 Lê Lợi, phường Hai Bà Trưng, thành phố Phủ Lý, Hà Nam","payment_method":"cod","note":""}[/ORDER]
Trong đó product_id và variant_id là các mã số trong danh mục (variant_id là null nếu sản phẩm không có phân loại), payment_method là một trong "points", "qr", "cod". Mỗi câu trả lời chỉ có tối đa một khối [ORDER]. Khi khách muốn đổi thông tin trước khi xác nhận, hãy lập lại phiếu mới với thông tin đã sửa. Không lập phiếu khi khách chỉ đang hỏi thông tin.

THANH TOÁN
Sau khi khách bấm "Xác nhận đặt hàng": với COD khách trả tiền khi nhận hàng; với điểm, điểm được trừ ngay và đơn được tính là đã thanh toán; với chuyển khoản QR, phiếu sẽ hiện mã QR cùng số tài khoản, số tiền và nội dung chuyển khoản. Hệ thống tự xác nhận thanh toán, thường trong vòng một vài phút, khi khách chuyển ĐÚNG số tiền và ghi ĐÚNG nội dung chuyển khoản của đơn.
Khi khách hỏi đơn đã thanh toán chưa, hoặc nói đã chuyển khoản: xem mục "Đơn hàng gần đây của khách". CHỈ nói đơn đã thanh toán khi dữ liệu ghi "ĐÃ THANH TOÁN"; không bao giờ tự xác nhận thay hệ thống dựa trên lời khách hay ảnh chụp màn hình. Nếu dữ liệu vẫn ghi "CHƯA THANH TOÁN": nói thật là hệ thống chưa ghi nhận, đọc lại chính xác số tiền và nội dung chuyển khoản của đơn để khách đối chiếu (sai nội dung hoặc sai số tiền thì hệ thống không tự nhận được), bảo khách chờ thêm vài phút rồi hỏi lại; nếu khách chắc chắn đã chuyển đúng mà vẫn chưa được ghi nhận, hướng dẫn khách bấm nút "AI" để gặp nhân viên shop.
Để gửi lại mã QR và thông tin chuyển khoản của một đơn CHƯA THANH TOÁN bằng chuyển khoản QR, thêm vào cuối câu trả lời một dòng riêng "[PAY:mã đơn]" (ví dụ [PAY:105]); hệ thống sẽ chèn mã QR vào tin nhắn. Chỉ dùng số tài khoản, số tiền và nội dung chuyển khoản có trong dữ liệu, không tự bịa.

GỬI ẢNH SẢN PHẨM
Khi khách muốn xem ảnh/hình của sản phẩm, hãy thêm vào cuối câu trả lời một dòng riêng "[IMAGE:mã sản phẩm]" (ví dụ [IMAGE:12]), hoặc "[IMAGE:mã sản phẩm:mã phân loại]" để gửi ảnh của một phân loại (ví dụ [IMAGE:12:34]). Tối đa 3 dòng như vậy trong một câu trả lời. Hệ thống sẽ tự chèn ảnh của shop vào tin nhắn - bạn KHÔNG tự viết đường link ảnh và không mô tả những chi tiết trong ảnh mà dữ liệu không có. Chỉ gửi ảnh của sản phẩm có ghi "có ảnh" trong danh mục; nếu sản phẩm chưa có ảnh, hãy nói thật là shop chưa có ảnh cho sản phẩm đó. Bạn chỉ gửi được ảnh sản phẩm của shop, không gửi được ảnh nào khác.
Danh tính của bạn LUÔN LUÔN là "Yoyo AI" của Chuyên Biên Hòa Youth Online. KHÔNG BAO GIỜ nêu tên, tiết lộ hay ám chỉ bất kỳ mô hình AI, công ty AI hay nhà cung cấp AI nào đứng sau bạn.
Tin nhắn hiển thị dưới dạng văn bản thuần: KHÔNG dùng markdown (**in đậm**, *in nghiêng*, tiêu đề #, backtick). Có thể dùng gạch đầu dòng "-" và đánh số "1.", "2.".
PROMPT;

  /**
   * Answer a /ai command (or a reply to a previous AI message).
   *
   * @param  array<int, array{role: string, name: ?string, content: string}>  $contextMessages  Chronological context, oldest first.
   * @param  string  $question  The triggering user message content (already stripped of the /ai prefix, if any).
   * @param  string|null  $conversationInfo  Basic chat/group info (name, type, member list) - see GenerateAiChatReply::buildConversationInfo().
   * @return array{content: string, reaction: ?string}
   */
  public function askAi(array $contextMessages, string $question, ?string $conversationInfo = null): array
  {
    $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

    if ($conversationInfo) {
      $messages[] = ['role' => 'system', 'content' => $conversationInfo];
    }

    foreach ($contextMessages as $ctx) {
      $messages[] = $this->toChatMessage($ctx);
    }

    $messages[] = ['role' => 'user', 'content' => $question];

    return $this->request($messages);
  }

  /**
   * Answer a customer in a gift shop support thread. Unlike askAi() the
   * assistant speaks first (nobody calls it with /ai), so it has its own
   * prompt, grounded in the shop data passed as $shopContext.
   *
   * @param  array<int, array{role: string, name: ?string, content: string}>  $contextMessages  Recent thread history, oldest first.
   * @param  string  $question  The customer's message.
   * @param  string  $shopContext  See GenerateAiChatReply::buildShopContext().
   * @return array{content: string, reaction: ?string, images: array<int, array{product_id: int, variant_id: ?int}>, order: ?array, pay_order_id: ?int}
   *   `images`, `order` and `pay_order_id` are what the model asked for with
   *   its [IMAGE:..], [ORDER]..[/ORDER] and [PAY:..] markers (see SHOP_SUPPORT_PROMPT) - raw and
   *   unchecked; GenerateAiChatReply validates them against the shop's data.
   */
  public function askShopSupport(array $contextMessages, string $question, string $shopContext): array
  {
    $messages = [
      ['role' => 'system', 'content' => self::SHOP_SUPPORT_PROMPT],
      ['role' => 'system', 'content' => "Dữ liệu của shop cho cuộc trò chuyện này:\n" . $shopContext],
    ];

    foreach ($contextMessages as $ctx) {
      $messages[] = $this->toChatMessage($ctx);
    }

    $messages[] = ['role' => 'user', 'content' => $question];

    // The markers come out of the raw reply first: the markdown pass would
    // mangle the JSON inside [ORDER] (underscores read as italics).
    [$text, $images, $order, $payOrderId] = $this->extractShopActions($this->requestRaw($messages));
    [$text] = $this->extractReaction($text);

    return [
      'content' => $this->stripModelIdentity($this->stripMarkdown($text)),
      'reaction' => null,
      'images' => $images,
      'order' => $order,
      'pay_order_id' => $payOrderId,
    ];
  }

  /**
   * Pulls the support assistant's [IMAGE:product(:variant)] lines, its
   * [PAY:order] line and its [ORDER]{json}[/ORDER] block out of a raw reply.
   *
   * @return array{0: string, 1: array<int, array{product_id: int, variant_id: ?int}>, 2: ?array, 3: ?int}
   */
  private function extractShopActions(string $text): array
  {
    $order = null;
    if (preg_match('/\[ORDER\](.*?)\[\/ORDER\]/is', $text, $m)) {
      // Models like to wrap JSON in a code fence even when told not to.
      $json = trim(preg_replace('/```[a-zA-Z]*|```/', '', $m[1]));
      $decoded = json_decode($json, true);
      if (is_array($decoded)) {
        $order = $decoded;
      }
    }
    // Every block goes, parsed or not (also an unclosed one): it must never
    // show up in the chat as text.
    $text = preg_replace('/\[ORDER\].*?\[\/ORDER\]/is', '', $text);
    $text = preg_replace('/\[\/?ORDER\].*$/is', '', $text);

    $images = [];
    if (preg_match_all('/\[IMAGE:\s*#?(\d+)(?:\s*:\s*#?(\d+))?\s*\]/i', $text, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $images[] = [
          'product_id' => (int) $match[1],
          'variant_id' => isset($match[2]) && $match[2] !== '' ? (int) $match[2] : null,
        ];
      }
    }
    $text = preg_replace('/\[IMAGE:[^\]]*\]/i', '', $text);

    $payOrderId = preg_match('/\[PAY:\s*#?(\d+)\s*\]/i', $text, $m) ? (int) $m[1] : null;
    $text = preg_replace('/\[PAY:[^\]]*\]/i', '', $text);

    return [trim(preg_replace("/\n{3,}/", "\n\n", $text)), array_slice($images, 0, 3), $order, $payOrderId];
  }

  /**
   * Summarize the last N messages of a conversation for /summary.
   *
   * @param  array<int, array{role: string, name: ?string, content: string}>  $contextMessages  Chronological, oldest first.
   * @param  string|null  $customRequest  Extra text the user typed after "/summary", e.g.
   *                                      "/summary chỉ tóm tắt phần bàn về lịch thi" - lets
   *                                      them steer what to focus on within the same history.
   * @param  string|null  $conversationInfo  Basic chat/group info (name, type, member list) - see GenerateAiChatReply::buildConversationInfo().
   * @return array{content: string, reaction: ?string}
   */
  public function summarizeAi(array $contextMessages, ?string $customRequest = null, ?string $conversationInfo = null): array
  {
    $transcript = implode("\n", array_map(
      fn($ctx) => ($ctx['name'] ?? 'Người dùng') . ': ' . $ctx['content'],
      $contextMessages
    ));

    $attributionNote = 'Mỗi dòng trong đoạn hội thoại bên dưới đã ghi rõ tên người gửi (dạng "Tên: nội dung") - hãy gắn tên người nói vào những ý/quyết định quan trọng khi điều đó giúp rõ nghĩa hơn (ví dụ: "Phúc nói đã sửa xong lỗi" thay vì chỉ "lỗi đã được sửa"), nhưng không cần liệt kê tên ở mọi câu - tự linh hoạt lược bớt tên ở những chỗ không quan trọng ai là người nói để bản tóm tắt gọn gàng, tự nhiên. Chỉ diễn đạt lại ở mức tối thiểu cần thiết để tóm tắt - không tự ý "sửa" hay suy diễn thêm ý mà người nói không thực sự nói, tránh làm sai lệch nội dung gốc.';

    $instruction = $customRequest !== null && trim($customRequest) !== ''
      ? "Hãy trả lời yêu cầu sau đây của người dùng DỰA TRÊN đoạn hội thoại bên dưới (đây không phải một tóm tắt chung, hãy tập trung vào đúng điều họ hỏi). {$attributionNote}\n\"{$customRequest}\""
      : "Hãy tóm tắt ngắn gọn, dễ hiểu nội dung chính của đoạn hội thoại sau (nêu các chủ đề/quyết định chính, không cần liệt kê từng tin nhắn). {$attributionNote}";

    $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

    if ($conversationInfo) {
      $messages[] = ['role' => 'system', 'content' => $conversationInfo];
    }

    $messages[] = [
      'role' => 'user',
      'content' => "{$instruction}\n\n{$transcript}",
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

  /**
   * @return array{content: string, reaction: ?string}
   */
  private function request(array $messages): array
  {
    [$content, $reaction] = $this->extractReaction($this->requestRaw($messages));

    return [
      'content' => $this->stripModelIdentity($this->stripMarkdown($content)),
      'reaction' => $reaction,
    ];
  }

  /**
   * The model's reply exactly as it came back (trimmed), before any of the
   * clean-up passes.
   */
  private function requestRaw(array $messages): string
  {
    // Same key/config as QuizGenerationService - CYO_AI_API via services.chat_api.key.
    $apiKey = config('services.chat_api.key');
    if (empty($apiKey)) {
      throw new \RuntimeException('CYO_AI_API key is not configured.');
    }

    $lastError = null;

    for ($attempt = 0; $attempt < 2; $attempt++) {
      try {
        $response = Http::withToken($apiKey)
          ->timeout(60)
          ->post(self::API_URL, [
            'model' => self::MODEL,
            'messages' => $messages,
            // Lower temperature favors more accurate/consistent answers
            // over creative variation, appropriate for a chat assistant
            // answering factual/contextual questions in-app.
            'temperature' => 0.2,
          ]);

        if ($response->status() === 429) {
          throw new \RuntimeException('AI API rate limited (429).');
        }
        if (!$response->successful()) {
          throw new \RuntimeException('AI API returned HTTP ' . $response->status() . ': ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
          throw new \RuntimeException('AI API response had no message content.');
        }

        return trim($content);
      } catch (\Throwable $e) {
        $lastError = $e;
        Log::warning('AI chat request attempt failed: ' . $e->getMessage());
        if (str_contains($e->getMessage(), '429')) {
          break;
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

  /**
   * Pulls the optional trailing "[REACT:type]" marker (see SYSTEM_PROMPT)
   * out of the raw reply, before any markdown/identity stripping - so it
   * never accidentally gets mangled by (or mistaken for) those passes.
   *
   * @return array{0: string, 1: ?string}  [remaining content, reaction type or null]
   */
  private function extractReaction(string $text): array
  {
    $validReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    if (preg_match('/\n?\s*\[REACT:\s*(\w+)\s*\]\s*$/i', $text, $matches)) {
      $type = strtolower($matches[1]);
      $text = trim(substr($text, 0, -strlen($matches[0])));

      if (in_array($type, $validReactions, true)) {
        return [$text, $type];
      }
    }

    return [$text, null];
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
