<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates multiple-choice quiz questions via Groq's chat completions API
 * (OpenAI-compatible). The AI picks its own topic every time - only
 * difficulty and question count are ever dictated by the caller.
 */
class QuizGenerationService
{
  private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
  private const MODEL = 'openai/gpt-oss-20b';

  private const DIFFICULTY_LABELS = [
    'easy' => 'dễ',
    'medium' => 'trung bình',
    'hard' => 'khó',
  ];

  /**
   * @param  int  $count  Number of questions to generate
   * @param  string  $difficulty  easy|medium|hard
   * @return array{topic: string, questions: array}
   *
   * @throws \RuntimeException  If the API call fails or returns unusable JSON
   */
  public function generate(int $count, string $difficulty): array
  {
    $apiKey = config('services.groq.key');
    if (!$apiKey) {
      throw new \RuntimeException('AI_API key is not configured.');
    }

    $difficultyLabel = self::DIFFICULTY_LABELS[$difficulty] ?? 'trung bình';
    $prompt = $this->buildPrompt($count, $difficultyLabel);

    // One retry - the model occasionally wraps JSON in markdown fences or
    // returns a slightly malformed structure despite the instructions.
    $lastError = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
      try {
        $response = Http::withToken($apiKey)
          ->timeout(60)
          ->post(self::API_URL, [
            'model' => self::MODEL,
            'messages' => [
              ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
          ]);

        if (!$response->successful()) {
          throw new \RuntimeException('Groq API returned HTTP ' . $response->status() . ': ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (!$content) {
          throw new \RuntimeException('Groq API response had no message content.');
        }

        return $this->parseAndValidate($content, $count);
      } catch (\Throwable $e) {
        $lastError = $e;
        Log::warning('Quiz generation attempt failed: ' . $e->getMessage());
      }
    }

    throw new \RuntimeException('Không thể tạo câu hỏi từ AI: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  private function buildPrompt(int $count, string $difficultyLabel): string
  {
    return <<<PROMPT
Hãy tự chọn một chủ đề học thuật thú vị và phù hợp cho học sinh (ví dụ: lịch sử, địa lý, khoa học, toán học, văn học, giáo dục công dân, thời sự...), sau đó tạo {$count} câu hỏi trắc nghiệm về chủ đề đó ở mức độ {$difficultyLabel}.

Trả về kết quả dưới dạng một JSON object DUY NHẤT với cấu trúc chính xác sau, không thêm bất kỳ văn bản, markdown hay lời giải thích nào khác ngoài JSON:

{
  "topic": "tên chủ đề bạn đã chọn (string, ngắn gọn)",
  "questions": [
    {
      "id": 1,
      "question": "nội dung câu hỏi (string)",
      "options": ["A. ...", "B. ...", "C. ...", "D. ..."],
      "answer": "A",
      "explanation": "giải thích ngắn gọn lý do đáp án đúng (string)"
    }
  ]
}

Yêu cầu bắt buộc:
- Mảng "questions" phải có đúng {$count} phần tử, id đánh số từ 1 đến {$count}.
- "options" luôn có đúng 4 phần tử, mỗi phần tử bắt đầu bằng "A. ", "B. ", "C. " hoặc "D. ".
- "answer" chỉ được là một trong các ký tự: "A", "B", "C", "D".
- Chỉ trả về JSON thuần túy, không dùng thẻ markdown (```), không viết lời mở đầu hay kết luận.
PROMPT;
  }

  private function parseAndValidate(string $content, int $expectedCount): array
  {
    // Strip a stray ```json ... ``` fence if the model added one anyway.
    $cleaned = trim($content);
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/```\s*$/', '', $cleaned);

    $data = json_decode($cleaned, true);
    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
      throw new \RuntimeException('AI response was not valid quiz JSON.');
    }

    $topic = is_string($data['topic'] ?? null) ? trim($data['topic']) : 'Kiến thức tổng hợp';
    $questions = [];

    foreach (array_values($data['questions']) as $index => $q) {
      if (
        !is_array($q)
        || !isset($q['question'], $q['options'], $q['answer'])
        || !is_array($q['options'])
        || count($q['options']) !== 4
        || !in_array($q['answer'], ['A', 'B', 'C', 'D'], true)
      ) {
        continue;
      }

      $questions[] = [
        'id' => $index + 1,
        'question' => (string) $q['question'],
        'options' => array_values(array_map('strval', $q['options'])),
        'answer' => (string) $q['answer'],
        'explanation' => isset($q['explanation']) ? (string) $q['explanation'] : '',
      ];
    }

    if (count($questions) < min($expectedCount, 3)) {
      // Too many malformed/dropped questions to be usable.
      throw new \RuntimeException('AI returned too few valid questions (' . count($questions) . '/' . $expectedCount . ').');
    }

    return ['topic' => $topic ?: 'Kiến thức tổng hợp', 'questions' => $questions];
  }
}
