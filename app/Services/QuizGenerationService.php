<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates multiple-choice quiz questions by calling the Google AI Studio
 * (Gemini) API directly - one of up to 5 configured API keys (GEMINI_1..
 * GEMINI_5, see config('services.gemini.keys')) is picked at random per
 * request, with the rest used as backup if that key fails. Topic, grade
 * level and difficulty are all dictated by the caller.
 */
class QuizGenerationService
{
  private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
  private const MODEL = 'gemini-3.1-flash-lite';

  private const DIFFICULTY_LABELS = [
    'easy' => 'dễ',
    'medium' => 'trung bình',
    'hard' => 'khó',
  ];

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
    $keys = config('services.gemini.keys', []);
    if (empty($keys)) {
      throw new \RuntimeException('No GEMINI_1..GEMINI_5 API key is configured.');
    }
    // Randomize which key is tried first each request (spreads load across
    // all 5), keeping the rest in random order as backup if it fails.
    shuffle($keys);

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

    // Each batch numbers its own questions 1..N independently (see
    // parseAndValidate), so a top-up round's ids collide with the first
    // batch's (e.g. both start at 1) once merged above - renumber the
    // combined set here so this method's own output always has unique,
    // sequential ids regardless of what the caller happens to do with them.
    $result['questions'] = array_values(array_map(
      fn($q, $i) => array_merge($q, ['id' => $i + 1]),
      $result['questions'],
      array_keys($result['questions']),
    ));

    return $result;
  }

  /**
   * Requests one batch of $count questions in a single pass.
   */
  private function requestBatch(int $count, string $difficultyLabel, ?string $topic, string $grade, bool $isCustomTopic, array $keys, ?string $forcedTopic): array
  {
    $prompt = $this->buildPrompt($count, $difficultyLabel, $topic, $grade, $isCustomTopic);

    $url = sprintf(self::API_URL, self::MODEL);

    $lastError = null;
    foreach ($keys as $apiKey) {
      for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
          $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout(60)
            ->post($url, [
              'systemInstruction' => [
                'parts' => [
                  ['text' => "You are a raw JSON generator. NEVER write introductory text, markdown formatting, backticks, or conversational filler like 'I will now generate...'. Output ONLY valid JSON starting with '{' and ending with '}'."],
                ],
              ],
              'contents' => [
                [
                  'role' => 'user',
                  'parts' => [['text' => $prompt]],
                ],
              ],
              'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
              ],
            ]);

          if ($response->status() === 429) {
            throw new \RuntimeException('AI API rate limited (429) for this key.');
          }
          if (!$response->successful()) {
            throw new \RuntimeException('AI API returned HTTP ' . $response->status() . ': ' . $response->body());
          }

          $content = $response->json('candidates.0.content.parts.0.text');
          if (!$content) {
            throw new \RuntimeException('AI API response had no message content.');
          }

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

Cấu trúc JSON duy nhất cần trả về (bắt buộc bắt đầu bằng { và kết thúc bằng }, không markdown, không lời thoại):
{
  "topic": "{$topicName}",
  "questions": [
    {
      "id": 1,
      "question": "Nội dung câu hỏi",
      "options": ["A. Phương án 1", "B. Phương án 2", "C. Phương án 3", "D. Phương án 4"],
      "answer": "A",
      "explanation": "Giải thích cô đọng nhưng đầy đủ lý do chọn đáp án đúng và vì sao các phương án khác sai."
    }
  ]
}

Quy tắc bắt buộc:
1. "explanation": Viết khoảng 1-2 câu ngắn gọn nhưng đắt giá, nêu rõ công thức/bản chất kiến thức chính đủ để người học hiểu nguyên nhân đúng/sai.
2. "options": Mỗi lựa chọn chứa cụm từ hoặc con số chính xác, không viết lan man.
3. "answer": CHỈ chọn 1 trong 4 ký tự "A", "B", "C", "D".
4. Mảng "questions" phải chứa ĐÚNG {$count} phần tử.
5. Tạo JSON hoàn chỉnh trong MỘT LẦN DUY NHẤT (single pass), không suy luận hay tự đánh giá lại.
PROMPT;
  }

  private function parseAndValidate(string $content, int $expectedCount, ?string $forcedTopic): array
  {
    $cleaned = trim($content);

    // Strip markdown formatting if present
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);$cleaned = preg_replace('/```\s*$/', '', $cleaned);

    // Regex capture outer JSON object structure
    if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
      $cleaned = $matches[0];
    }

    $data = json_decode($cleaned, true);

    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
      Log::error('Quiz JSON Parse Failed. Raw content: ' . $content);
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