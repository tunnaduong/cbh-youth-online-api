<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates multiple-choice quiz questions via Groq's chat completions API
 * (OpenAI-compatible). Topic, grade level and difficulty are all dictated
 * by the caller - the AI only writes the questions.
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
   * @param  string|null  $topic  The subject/topic the questions must be about,
   *   or null to let the AI pick its own topic freely
   * @param  string  $grade  10|11|12
   * @return array{topic: string, questions: array}
   *
   * @throws \RuntimeException  If the API call fails or returns unusable JSON
   */
  public function generate(int $count, string $difficulty, ?string $topic, string $grade): array
  {
    $apiKey = config('services.groq.key');
    if (!$apiKey) {
      throw new \RuntimeException('AI_API key is not configured.');
    }

    $difficultyLabel = self::DIFFICULTY_LABELS[$difficulty] ?? 'trung bình';
    $prompt = $this->buildPrompt($count, $difficultyLabel, $topic, $grade);

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

        return $this->parseAndValidate($content, $count, $topic);
      } catch (\Throwable $e) {
        $lastError = $e;
        Log::warning('Quiz generation attempt failed: ' . $e->getMessage());
      }
    }

    throw new \RuntimeException('Không thể tạo câu hỏi từ AI: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  private function buildPrompt(int $count, string $difficultyLabel, ?string $topic, string $grade): string
  {
    $topicInstruction = $topic
      ? "Bạn là giáo viên THPT tại Việt Nam đang ra đề kiểm tra. Hãy tạo {$count} câu hỏi trắc nghiệm bám sát SÁCH GIÁO KHOA chương trình môn \"{$topic}\" lớp {$grade} hiện hành của Bộ Giáo dục và Đào tạo Việt Nam, ở mức độ {$difficultyLabel}."
      : "Bạn là giáo viên THPT tại Việt Nam đang ra đề kiểm tra. Hãy tự chọn một môn học trong chương trình lớp {$grade} (ví dụ: Toán, Vật lý, Hóa học, Sinh học, Ngữ văn, Lịch sử, Địa lý, Tin học...), sau đó tạo {$count} câu hỏi trắc nghiệm bám sát SÁCH GIÁO KHOA chương trình môn đó lớp {$grade} hiện hành của Bộ Giáo dục và Đào tạo Việt Nam, ở mức độ {$difficultyLabel}.";
    $topicFieldRule = $topic
      ? "- \"topic\" phải là đúng chuỗi \"{$topic}\"."
      : "- \"topic\" là tên môn học bạn đã tự chọn (string, ngắn gọn).";
    $topicFocusRule = $topic
      ? "- Câu hỏi phải bám sát chủ đề \"{$topic}\" và đúng nội dung sách giáo khoa lớp {$grade}, không hỏi kiến thức của lớp khác."
      : "- Câu hỏi phải đúng nội dung sách giáo khoa lớp {$grade}, không hỏi kiến thức của lớp khác.";
    $curriculumRule = "- Câu hỏi PHẢI theo đúng chương trình sách giáo khoa Việt Nam hiện hành cho lớp {$grade} - không hỏi kiến thức quá cơ bản/tiểu học hoặc lệch chương trình. Mức độ \"{$difficultyLabel}\" nghĩa là {$difficultyLabel} SO VỚI học sinh lớp {$grade} (câu \"khó\" phải thực sự thử thách học sinh giỏi ở lớp này, không phải câu hỏi thường thức dễ đoán).";

    // Foreign-language subjects (Tiếng Anh, Tiếng Nga, Tiếng Pháp, ...) are
    // allowed to have "question"/"options" written in that language - the
    // whole point is testing that language. "explanation" always stays
    // Vietnamese regardless. Every other subject stays fully Vietnamese.
    $isForeignLanguageTopic = $topic && preg_match('/tiếng/iu', $topic) && !preg_match('/tiếng việt/iu', $topic);
    $languageRule = $isForeignLanguageTopic
      ? "- Vì đây là môn ngoại ngữ (\"{$topic}\"), \"question\" và \"options\" được phép viết bằng ngôn ngữ đó (ví dụ tiếng Anh, tiếng Nga...) khi phù hợp với nội dung câu hỏi. Riêng \"explanation\" LUÔN LUÔN phải viết bằng tiếng Việt."
      : "- TOÀN BỘ nội dung (\"topic\", \"question\", \"options\", \"explanation\") phải được viết bằng tiếng Việt, không dùng tiếng Anh hay bất kỳ ngôn ngữ nào khác. Ngoại lệ duy nhất: nếu chủ đề là một môn ngoại ngữ (ví dụ Tiếng Anh, Tiếng Nga...) thì \"question\" và \"options\" được phép dùng ngôn ngữ đó, còn \"explanation\" vẫn luôn phải là tiếng Việt.";

    return <<<PROMPT
{$topicInstruction}

Trả về kết quả dưới dạng một JSON object DUY NHẤT với cấu trúc chính xác sau, không thêm bất kỳ văn bản, markdown hay lời giải thích nào khác ngoài JSON:

{
  "topic": "tên chủ đề (string, ngắn gọn)",
  "questions": [
    {
      "id": 1,
      "question": "nội dung câu hỏi (string)",
      "options": ["A. ...", "B. ...", "C. ...", "D. ..."],
      "answer": "A",
      "explanation": "giải thích ngắn gọn lý do đáp án đúng, luôn bằng tiếng Việt (string)"
    }
  ]
}

Yêu cầu bắt buộc:
{$languageRule}
- Mảng "questions" phải có đúng {$count} phần tử, id đánh số từ 1 đến {$count}.
{$topicFocusRule}
{$curriculumRule}
{$topicFieldRule}
- "options" luôn có đúng 4 phần tử, mỗi phần tử bắt đầu bằng "A. ", "B. ", "C. " hoặc "D. ".
- "answer" chỉ được là một trong các ký tự: "A", "B", "C", "D".
- Chỉ trả về JSON thuần túy, không dùng thẻ markdown (```), không viết lời mở đầu hay kết luận.
PROMPT;
  }

  private function parseAndValidate(string $content, int $expectedCount, ?string $forcedTopic): array
  {
    // Strip a stray ```json ... ``` fence if the model added one anyway.
    $cleaned = trim($content);
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/```\s*$/', '', $cleaned);

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
      // Too many malformed/dropped questions to be usable.
      throw new \RuntimeException('AI returned too few valid questions (' . count($questions) . '/' . $expectedCount . ').');
    }

    return ['topic' => $topic, 'questions' => $questions];
  }
}
