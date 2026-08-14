<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parses a creator's own quiz content (typed with bold/underline marking the
 * correct answer, or extracted from an uploaded .docx/.txt/.pdf) into
 * structured multiple-choice questions, via the same Groq AI used for
 * QuizGenerationService - but this one only EXTRACTS what's already in the
 * source text, it never invents questions/facts. See CustomQuizController.
 */
class CustomQuizParsingService
{
  private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
  // Per product decision: custom-quiz parsing intentionally stays on
  // gpt-oss-120b (unlike QuizGenerationService's model, which has since
  // moved on) - this is pure extraction of existing text, not open-ended
  // generation, so it doesn't need the same factual-recall bar.
  private const MODEL = 'openai/gpt-oss-120b';

  private const MAX_CONTENT_CHARS = 24000;

  /**
   * @param  string  $markdown  Source content with **bold** and/or
   *   <u>underline</u> marking the correct option per question (see
   *   QuizDocumentExtractionService), already trimmed to a reasonable size.
   * @return array{questions: array}
   *
   * @throws \RuntimeException  If every API key fails, or nothing usable
   *   was extracted from the content.
   */
  public function parse(string $markdown): array
  {
    $keys = array_values(array_filter([
      config('services.groq.key'),
      config('services.groq.secondary_key'),
    ]));
    if (empty($keys)) {
      throw new \RuntimeException('AI_API key is not configured.');
    }

    $markdown = mb_substr(trim($markdown), 0, self::MAX_CONTENT_CHARS);
    if ($markdown === '') {
      throw new \RuntimeException('Nội dung trống, không có gì để phân tích.');
    }

    $prompt = $this->buildPrompt($markdown);

    $lastError = null;
    foreach ($keys as $apiKey) {
      for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
          $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post(self::API_URL, [
              'model' => self::MODEL,
              'messages' => [
                ['role' => 'user', 'content' => $prompt],
              ],
              'temperature' => 0.1,
              'response_format' => ['type' => 'json_object'],
            ]);

          if ($response->status() === 429) {
            throw new \RuntimeException('Groq API rate limited (429) for this key.');
          }
          if (!$response->successful()) {
            throw new \RuntimeException('Groq API returned HTTP ' . $response->status() . ': ' . $response->body());
          }

          $content = $response->json('choices.0.message.content');
          if (!$content) {
            throw new \RuntimeException('Groq API response had no message content.');
          }

          return $this->parseAndValidate($content);
        } catch (\Throwable $e) {
          $lastError = $e;
          Log::warning('Custom quiz parsing attempt failed: ' . $e->getMessage());
          if (str_contains($e->getMessage(), '429')) {
            break;
          }
        }
      }
    }

    throw new \RuntimeException('Không thể phân tích nội dung: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  private function buildPrompt(string $markdown): string
  {
    return <<<PROMPT
Bạn là một công cụ TRÍCH XUẤT dữ liệu, KHÔNG PHẢI công cụ sáng tạo nội dung. Người dùng đã tự soạn sẵn một đề trắc nghiệm (có thể gõ tay hoặc trích từ file .docx/.txt/.pdf họ tải lên) và đánh dấu đáp án đúng trong mỗi câu hỏi bằng MỘT trong các cách: chữ IN ĐẬM (**như vậy**), chữ GẠCH CHÂN (<u>như vậy</u>), hoặc chữ có MÀU KHÁC BIỆT so với các phương án còn lại (đánh dấu bằng <mark>như vậy</mark> - màu gì cũng được, không nhất thiết phải là màu đỏ, miễn là nó khác màu với 3 phương án kia). Nhiệm vụ của bạn CHỈ là đọc và trích xuất lại đúng những gì đã có trong văn bản thành JSON có cấu trúc - TUYỆT ĐỐI KHÔNG được tự bịa thêm câu hỏi, đáp án, hay giải thích nào không có trong văn bản gốc. CHỈ chấp nhận câu hỏi trắc nghiệm (nhiều lựa chọn) - bỏ qua mọi dạng câu hỏi khác (tự luận, điền khuyết, đúng/sai không có lựa chọn...).

Nội dung nguồn:
---
{$markdown}
---

Quy tắc trích xuất:
- Với mỗi câu hỏi trắc nghiệm tìm thấy trong văn bản: xác định phần câu hỏi, các phương án trả lời (thường 4, nhưng có thể chỉ có 2 hoặc 3), và phương án nào được đánh dấu (in đậm, gạch chân, HOẶC màu chữ khác biệt) là đáp án đúng. Hãy kiểm tra CẨN THẬN từng phương án trong 4 phương án để tìm đúng phương án nào được đánh dấu khác với 3 phương án còn lại.
- CHỈ giữ lại những câu hỏi có TỪ 2 ĐẾN 4 phương án trả lời VÀ có ĐÚNG MỘT phương án được đánh dấu rõ ràng là đáp án đúng (in đậm, gạch chân, hoặc màu khác biệt). Nếu một câu có nhiều hơn 1 phương án được đánh dấu, có ít hơn 2 hoặc nhiều hơn 4 phương án, hoặc không phương án nào được đánh dấu và cũng không có cách nào khác trong văn bản (ví dụ dòng "Đáp án: B", "Answer: C") để xác định đáp án đúng - hãy BỎ QUA câu đó hoàn toàn, không đoán mò, không tự chọn đại một phương án.
- Nếu một câu chỉ có 2 hoặc 3 phương án thật sự trong văn bản (không phải 4), vẫn giữ câu đó: đưa đúng số phương án có trong mảng "options" theo thứ tự gốc, các vị trí còn thiếu (để đủ 4 phần tử) điền chuỗi rỗng "".
- Nếu văn bản có phần giải thích cho câu hỏi (thường nằm ngay sau phương án, có thể có nhãn như "Giải thích:", "Lý do:", ghi trong ngoặc...) thì lấy nguyên văn phần đó làm "explanation". Nếu không có, để "explanation" là chuỗi rỗng "" - KHÔNG tự viết giải thích mới.
- Bỏ các ký tự đánh dấu định dạng (**, <u>, </u>, <mark>, </mark>, __, ...) khỏi nội dung câu hỏi/phương án/giải thích trong JSON đầu ra - JSON chỉ chứa văn bản thuần, không chứa markup.
- Giữ nguyên văn phong, ngôn ngữ (Việt/Anh/...) và nội dung gốc của người dùng - không dịch, không diễn giải lại, không sửa lỗi chính tả trừ khi rõ ràng là lỗi gõ phím vô nghĩa.
- Nếu văn bản nguồn không chứa bất kỳ câu hỏi trắc nghiệm hợp lệ nào (ví dụ không có đánh dấu đáp án nào cả), trả về mảng "questions" rỗng.

Trả về JSON DUY NHẤT theo cấu trúc chính xác sau, không thêm văn bản/markdown nào khác ngoài JSON:
{
  "questions": [
    {
      "question": "nội dung câu hỏi (string, không markup)",
      "options": ["nội dung phương án A", "nội dung phương án B", "nội dung phương án C", "nội dung phương án D hoặc \"\" nếu câu chỉ có 2-3 phương án"],
      "answer": "A",
      "explanation": ""
    }
  ]
}

- "options" LUÔN LUÔN là mảng đúng 4 phần tử (điền "" cho vị trí thiếu nếu câu chỉ có 2-3 phương án thật), theo đúng thứ tự A, B, C, D như trong văn bản gốc (không tự thêm tiền tố "A. "/"B. ..." nếu văn bản gốc không có, giữ nguyên định dạng gốc của từng phương án).
- "answer" là một trong "A", "B", "C", "D" tương ứng với VỊ TRÍ (thứ 1/2/3/4) của phương án được đánh dấu trong mảng "options", không phải theo chữ cái gốc ghi trong văn bản (nếu văn bản gốc dùng số 1/2/3/4 hoặc a)/b)/c)/d) thay vì A/B/C/D, vẫn quy đổi "answer" về A/B/C/D theo vị trí).
- Chỉ trả về JSON thuần túy, không dùng thẻ markdown (```), không viết lời mở đầu hay kết luận.
PROMPT;
  }

  private function parseAndValidate(string $content): array
  {
    $cleaned = trim($content);
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/```\s*$/', '', $cleaned);

    $data = json_decode($cleaned, true);
    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
      throw new \RuntimeException('AI response was not valid quiz JSON.');
    }

    $questions = [];
    foreach (array_values($data['questions']) as $index => $q) {
      if (
        !is_array($q)
        || !isset($q['question'], $q['options'], $q['answer'])
        || !is_array($q['options'])
        || count($q['options']) < 2
        || count($q['options']) > 4
        || !in_array($q['answer'], ['A', 'B', 'C', 'D'], true)
        || trim((string) $q['question']) === ''
      ) {
        continue;
      }

      $options = array_values(array_map('strval', $q['options']));
      $answerIndex = array_search($q['answer'], ['A', 'B', 'C', 'D'], true);
      if ($answerIndex >= count($options)) {
        continue; // "answer" points past the (fewer than 4) options actually given
      }
      while (count($options) < 4) {
        $options[] = '';
      }

      $questions[] = [
        'id' => count($questions) + 1,
        'question' => (string) $q['question'],
        'options' => $options,
        'answer' => (string) $q['answer'],
        'explanation' => isset($q['explanation']) ? (string) $q['explanation'] : '',
      ];
    }

    if (empty($questions)) {
      throw new \RuntimeException('Không tìm thấy câu hỏi hợp lệ nào (cần đánh dấu in đậm/gạch chân/màu khác biệt cho đáp án đúng, có từ 2-4 phương án).');
    }

    return ['questions' => $questions];
  }
}
