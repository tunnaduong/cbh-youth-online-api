<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates multiple-choice quiz questions via the configured chat-api
 * OpenAI-compatible endpoint. Topic, grade level and difficulty are all
 * dictated by the caller.
 */
class QuizGenerationService
{
  private const API_URL = 'https://chat-api.chuyenbienhoa.com/v1/chat/completions';
  private const MODEL = 'gemini-flash-lite';

  private const DIFFICULTY_LABELS = [
    'easy' => 'dễ',
    'medium' => 'trung bình',
    'hard' => 'khó',
  ];

  // Restrict top-up calls to 1 round max to eliminate compounding back-to-back request delays
  private const MAX_TOPUP_ROUNDS = 1;

  /**
   * @param  int  $count  Number of questions to generate
   * @param  string  $difficulty  easy|medium|hard
   * @param  string|null  $topic  The subject/topic
   * @param  string  $grade  10|11|12
   * @param  bool  $isCustomTopic  True when $topic is user's free-text topic
   * @return array{topic: string, questions: array}
   *
   * @throws \RuntimeException  If generation fails completely
   */
  public function generate(int $count, string $difficulty, ?string $topic, string $grade, bool $isCustomTopic = false): array
  {
    $apiKey = config('services.chat_api.key');
    if (empty($apiKey)) {
      throw new \RuntimeException('CYO_AI_API key is not configured.');
    }

    $keys = [$apiKey];
    $difficultyLabel = self::DIFFICULTY_LABELS[$difficulty] ?? 'trung bình';

    $result = $this->requestBatch($count, $difficultyLabel, $topic, $grade, $isCustomTopic, $keys, $topic);

    for ($round = 0; count($result['questions']) < $count && $round < self::MAX_TOPUP_ROUNDS; $round++) {
      $missing = $count - count($result['questions']);
      try {
        $topUp = $this->requestBatch($missing, $difficultyLabel, $result['topic'], $grade, $isCustomTopic, $keys, $result['topic']);
        $result['questions'] = array_merge($result['questions'], $topUp['questions']);
      } catch (\Throwable $e) {
        Log::warning('Quiz top-up generation failed, using what was collected so far: ' . $e->getMessage());
        break;
      }
    }

    return $result;
  }

  /**
   * Requests one batch of $count questions in a single pass.
   */
  private function requestBatch(int $count, string $difficultyLabel, ?string $topic, string $grade, bool $isCustomTopic, array $keys, ?string $forcedTopic): array
  {
    $prompt = $this->buildPrompt($count, $difficultyLabel, $topic, $grade, $isCustomTopic);

    $lastError = null;
    foreach ($keys as $apiKey) {
      for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
          $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::API_URL, [
              'model' => self::MODEL,
              'messages' => [
                ['role' => 'user', 'content' => $prompt],
              ],
              'temperature' => 0.1,
              'response_format' => ['type' => 'json_object'],
            ]);

          if ($response->status() === 429) {
            throw new \RuntimeException('AI API rate limited (429) for this key.');
          }
          if (!$response->successful()) {
            throw new \RuntimeException('AI API returned HTTP ' . $response->status() . ': ' . $response->body());
          }

          $content = $response->json('choices.0.message.content');
          if (!$content) {
            throw new \RuntimeException('AI API response had no message content.');
          }

          // Directly parse and return in a single pass
          return $this->parseAndValidate($content, $count, $forcedTopic);
        } catch (\Throwable $e) {
          $lastError = $e;
          Log::warning('Quiz generation attempt failed: ' . $e->getMessage());
          if (str_contains($e->getMessage(), '429')) {
            break;
          }
        }
      }
    }

    throw new \RuntimeException('Không thể tạo câu hỏi từ AI: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  private function buildPrompt(int $count, string $difficultyLabel, ?string $topic, string $grade, bool $isCustomTopic = false): string
  {
    $topicName = $topic ?: 'Kiến thức tổng hợp';
    $contextScope = $isCustomTopic 
      ? "Chủ đề tùy chỉnh: \"{$topicName}\"." 
      : "Sách giáo khoa môn \"{$topicName}\" lớp {$grade} (Bộ GD&ĐT Việt Nam).";

    return <<<PROMPT
Tạo ĐÚNG {$count} câu hỏi trắc nghiệm tiếng Việt dành cho học sinh lớp {$grade}, mức độ {$difficultyLabel}.
Bối cảnh: {$contextScope}

Cấu trúc JSON duy nhất cần trả về (không markdown, không thêm văn bản):
{
  "topic": "{$topicName}",
  "questions": [
    {
      "id": 1,
      "question": "Nội dung câu hỏi ngắn gọn",
      "options": ["A. Đáp án 1", "B. Đáp án 2", "C. Đáp án 3", "D. Đáp án 4"],
      "answer": "A",
      "explanation": "Từ khóa cốt lõi"
    }
  ]
}

Quy tắc tối ưu token và tốc độ tối đa:
1. "question": Nêu câu hỏi súc tích, ngắn gọn.
2. "options": Mỗi lựa chọn chỉ chứa cụm từ/con số ngắn, không viết thành câu dài.
3. "explanation": TỐI ĐA 5-8 TỪ. Chỉ viết từ khóa/công thức chính giải thích lý do đúng, không viết câu đầy đủ.
4. "answer": Bắt buộc chọn 1 trong 4 ký tự "A", "B", "C", "D".
5. Tạo JSON hoàn chỉnh trong MỘT LẦN DUY NHẤT (single pass), không suy luận hay tự đánh giá lại.
PROMPT;
  }

  private function parseAndValidate(string $content, int $expectedCount, ?string $forcedTopic): array
  {
    $cleaned = trim($content);
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);$cleaned = preg_replace('/```\s*$/', '', $cleaned);

    $data = json_decode($cleaned, true);
    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
      throw new \RuntimeException('AI response was not valid quiz JSON.');
    }

    $topic = $forcedTopic ?: (is_string($data['topic'] ?? null) && trim($data['topic']) !== ''
      ? trim($data['topic'])
      : 'Kiến thức tổng hợp');

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
      throw new \RuntimeException('AI returned too few valid questions (' . count($questions) . '/' . $expectedCount . ').');
    }

    return ['topic' => $topic, 'questions' => $questions];
  }
}