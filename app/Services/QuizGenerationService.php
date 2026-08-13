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
  private const MODEL = 'openai/gpt-oss-120b';

  private const DIFFICULTY_LABELS = [
    'easy' => 'dễ',
    'medium' => 'trung bình',
    'hard' => 'khó',
  ];

  // How many extra rounds we'll ask the AI to top up a short batch (e.g. it
  // was asked for 50 and only returned 20) before giving up with whatever
  // was collected. QuizController still falls back to the local bank if
  // even the first batch fails outright.
  private const MAX_TOPUP_ROUNDS = 3;

  /**
   * @param  int  $count  Number of questions to generate
   * @param  string  $difficulty  easy|medium|hard
   * @param  string|null  $topic  The subject/topic the questions must be about,
   *   or null to let the AI pick its own topic freely
   * @param  string  $grade  10|11|12
   * @param  bool  $isCustomTopic  True when $topic is the user's own free-text
   *   topic (the "Khác" option) rather than one of the predefined SGK subjects
   * @return array{topic: string, questions: array}
   *
   * @throws \RuntimeException  If every API key fails and no questions were produced
   */
  public function generate(int $count, string $difficulty, ?string $topic, string $grade, bool $isCustomTopic = false): array
  {
    // Primary key first, then the backup key (AI_API_DHPHUC) if the
    // primary is unset/rate-limited/erroring out.
    $keys = array_values(array_filter([
      config('services.groq.key'),
      config('services.groq.secondary_key'),
    ]));
    if (empty($keys)) {
      throw new \RuntimeException('AI_API key is not configured.');
    }

    $difficultyLabel = self::DIFFICULTY_LABELS[$difficulty] ?? 'trung bình';

    $result = $this->requestBatch($count, $difficultyLabel, $topic, $grade, $isCustomTopic, $keys, $topic);

    // The model sometimes returns fewer questions than asked for (e.g. asked
    // for 50, only got 20) - keep asking it for exactly the remainder,
    // locked to the same resolved topic, until the count is met.
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
   * Requests one batch of $count questions, trying each API key in order
   * (each with one retry for malformed JSON) before giving up.
   */
  private function requestBatch(int $count, string $difficultyLabel, ?string $topic, string $grade, bool $isCustomTopic, array $keys, ?string $forcedTopic): array
  {
    $prompt = $this->buildPrompt($count, $difficultyLabel, $topic, $grade, $isCustomTopic);

    $lastError = null;
    foreach ($keys as $apiKey) {
      // One retry per key - the model occasionally wraps JSON in markdown
      // fences or returns a slightly malformed structure despite instructions.
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
            // Rate limited - move on to the next key immediately instead of
            // burning the retry on the same one.
            throw new \RuntimeException('Groq API rate limited (429) for this key.');
          }
          if (!$response->successful()) {
            throw new \RuntimeException('Groq API returned HTTP ' . $response->status() . ': ' . $response->body());
          }

          $content = $response->json('choices.0.message.content');
          if (!$content) {
            throw new \RuntimeException('Groq API response had no message content.');
          }

          return $this->parseAndValidate($content, $count, $forcedTopic);
        } catch (\Throwable $e) {
          $lastError = $e;
          Log::warning('Quiz generation attempt failed: ' . $e->getMessage());
          if (str_contains($e->getMessage(), '429')) {
            break; // don't retry a rate-limited key, fall through to the next one
          }
        }
      }
    }

    throw new \RuntimeException('Không thể tạo câu hỏi từ AI: ' . ($lastError?->getMessage() ?? 'unknown error'));
  }

  private function buildPrompt(int $count, string $difficultyLabel, ?string $topic, string $grade, bool $isCustomTopic = false): string
  {
    if ($isCustomTopic && $topic) {
      // The user typed this topic themselves - it might not even be a school
      // subject (e.g. "Anime", "Bóng đá") so we don't force the SGK/textbook
      // framing on it, but it MUST be followed exactly, no substituting a
      // different topic.
      $topicInstruction = "Người dùng đã yêu cầu một chủ đề tùy chỉnh: \"{$topic}\". Hãy tạo ĐÚNG {$count} câu hỏi trắc nghiệm (không nhiều hơn, không ít hơn {$count} câu) ĐÚNG về chủ đề này, phù hợp với học sinh lớp {$grade}, ở mức độ {$difficultyLabel}.";
      $topicFocusRule = "- BẮT BUỘC bám sát 100% chủ đề \"{$topic}\" mà người dùng đã chọn - TUYỆT ĐỐI không tự ý đổi sang chủ đề khác, không lạc đề, kể cả khi chủ đề nghe lạ hoặc hẹp. Chỉ điều chỉnh độ khó/cách diễn đạt cho phù hợp lứa tuổi lớp {$grade}, không được từ chối hay thay thế chủ đề.";
      $curriculumRule = "- Mức độ \"{$difficultyLabel}\" nghĩa là {$difficultyLabel} SO VỚI học sinh lớp {$grade}.";
    } else {
      $topicInstruction = $topic
        ? "Bạn là giáo viên THPT tại Việt Nam đang ra đề kiểm tra. Hãy tạo ĐÚNG {$count} câu hỏi trắc nghiệm (không nhiều hơn, không ít hơn {$count} câu) bám sát SÁCH GIÁO KHOA chương trình môn \"{$topic}\" lớp {$grade} hiện hành của Bộ Giáo dục và Đào tạo Việt Nam, ở mức độ {$difficultyLabel}."
        : "Bạn là giáo viên THPT tại Việt Nam đang ra đề kiểm tra. Hãy tự chọn một môn học trong chương trình lớp {$grade} (ví dụ: Toán, Vật lý, Hóa học, Sinh học, Ngữ văn, Lịch sử, Địa lý, Tin học...), sau đó tạo {$count} câu hỏi trắc nghiệm bám sát SÁCH GIÁO KHOA chương trình môn đó lớp {$grade} hiện hành của Bộ Giáo dục và Đào tạo Việt Nam, ở mức độ {$difficultyLabel}.";
      $topicFocusRule = $topic
        ? "- Câu hỏi phải bám sát chủ đề \"{$topic}\" và đúng nội dung sách giáo khoa lớp {$grade}, không hỏi kiến thức của lớp khác."
        : "- Câu hỏi phải đúng nội dung sách giáo khoa lớp {$grade}, không hỏi kiến thức của lớp khác.";
      $curriculumRule = "- Câu hỏi PHẢI theo đúng chương trình sách giáo khoa Việt Nam hiện hành cho lớp {$grade} - không hỏi kiến thức quá cơ bản/tiểu học hoặc lệch chương trình. Mức độ \"{$difficultyLabel}\" nghĩa là {$difficultyLabel} SO VỚI học sinh lớp {$grade} (câu \"khó\" phải thực sự thử thách học sinh giỏi ở lớp này, không phải câu hỏi thường thức dễ đoán).";
    }

    $topicFieldRule = $topic
      ? "- \"topic\" phải là đúng chuỗi \"{$topic}\"."
      : "- \"topic\" là tên môn học bạn đã tự chọn (string, ngắn gọn).";

    // Distractor plausibility should scale with difficulty: easy questions
    // want obviously-wrong distractors, hard questions want distractors that
    // are close/plausible (common misconceptions, near-miss values, similar
    // terms) so the question is genuinely challenging - but exactly one
    // option must still be fully, unambiguously correct. This is "make it
    // tricky", not "make the answer debatable".
    $distractorRule = match ($difficultyLabel) {
      'khó' => "- Vì đây là câu hỏi mức độ KHÓ: 3 phương án nhiễu PHẢI thực sự \"gần đúng\" và dễ gây nhầm lẫn - ví dụ đại diện cho lỗi sai/ngộ nhận phổ biến của học sinh, số liệu/mốc thời gian/tên gần giống đáp án đúng, hoặc đúng trong một trường hợp khác dễ bị nhầm sang trường hợp này. Chúng phải khiến người không nắm chắc kiến thức dễ chọn nhầm. TUY NHIÊN, khi xét kỹ và đối chiếu chính xác với kiến thức chuẩn, PHẢI có DUY NHẤT MỘT phương án đúng hoàn toàn, còn 3 phương án kia phải sai dứt khoát, có căn cứ rõ ràng để loại trừ (không phải \"cũng tạm coi là đúng\" hay để ngỏ nhiều cách hiểu). Được phép đánh đố bằng logic, nhưng bản thân bạn (người ra đề) không được đoán mò - chỉ dùng những nhầm lẫn/kiến thức bạn chắc chắn xác định được là sai.",
      'trung bình' => "- 3 phương án nhiễu nên tương đối hợp lý, liên quan đến chủ đề (không phải phương án vô nghĩa dễ loại ngay), nhưng vẫn phải sai rõ ràng và chắc chắn khi đối chiếu kiến thức chuẩn - không tạo cảm giác \"có thể đúng\".",
      default => "- 3 phương án nhiễu nên liên quan đến chủ đề nhưng sai một cách rõ ràng, dễ phân biệt với đáp án đúng, phù hợp mức độ dễ.",
    };

    // A topic gets to use another language in "question"/"options" only when
    // it's actually ABOUT a language (Tiếng Anh, "học tiếng Nhật", "ngữ pháp
    // English", ...) - the whole point there is testing that language. Any
    // other topic (predefined subject OR custom, e.g. "Anime", "Bóng đá")
    // stays Vietnamese-only, no matter how unusual the topic is.
    $isForeignLanguageTopic = $topic && $this->looksLikeLanguageTopic($topic);
    $languageRule = $isForeignLanguageTopic
      ? "- Vì chủ đề này liên quan đến một ngoại ngữ (\"{$topic}\"), \"question\" và \"options\" được phép viết bằng ngôn ngữ đó (ví dụ tiếng Anh, tiếng Nga...) khi phù hợp với nội dung câu hỏi. Riêng \"explanation\" LUÔN LUÔN phải viết bằng tiếng Việt."
      : "- Chủ đề này KHÔNG liên quan đến việc học ngoại ngữ, vì vậy TOÀN BỘ nội dung (\"topic\", \"question\", \"options\", \"explanation\") phải được viết bằng tiếng Việt, không dùng tiếng Anh hay bất kỳ ngôn ngữ nào khác, kể cả khi bản thân chủ đề có nguồn gốc nước ngoài (ví dụ tên phim, tên trò chơi...).";

    // History is where this model hallucinates the most (exact dates, troop
    // counts, minor treaty clauses, mixing up similarly-named
    // people/battles/campaigns). Lock it down hard: only iconic, undisputed
    // milestones, no invented precision.
    $isHistoryTopic = $topic && $this->looksLikeHistoryTopic($topic);
    $historyRule = $isHistoryTopic
      ? "\n\nQUY TẮC RIÊNG CHO MÔN LỊCH SỬ (bắt buộc tuân thủ nghiêm ngặt, ưu tiên cao nhất):\n- CHỈ hỏi về những sự kiện, mốc thời gian, nhân vật lớn, mang tính CỘT MỐC, được ghi rõ và thống nhất trong sách giáo khoa Lịch sử Việt Nam hiện hành - loại bỏ hoàn toàn các chi tiết nhỏ, số liệu lẻ (số quân, số thương vong, số ngày cụ thể của một trận đánh phụ...) trừ khi đó là con số/ngày tháng NỔI TIẾNG, được nhắc đi nhắc lại trong sách giáo khoa (ví dụ 2/9/1945, 7/5/1954, 30/4/1975 là chấp nhận được vì đây là mốc cực kỳ phổ biến).\n- TUYỆT ĐỐI KHÔNG tự suy luận hay ước lượng ngày tháng, số liệu, tên hiệp ước/hiệp định, tên nhân vật nếu không nhớ chắc chắn 100% - không được làm tròn, đoán gần đúng hoặc suy ra từ sự kiện liên quan rồi coi như chắc chắn.\n- Cẩn thận phân biệt các sự kiện/nhân vật/chiến dịch có tên gần giống nhau hoặc cùng thời kỳ (dễ nhầm lẫn) - nếu không chắc chắn phân biệt được rạch ròi, hãy đổi sang hỏi một sự kiện cột mốc khác chắc chắn hơn thay vì hỏi thứ dễ gây tranh cãi.\n- Với câu hỏi mức khó về Lịch sử: đánh đố bằng cách yêu cầu học sinh phân biệt hai sự kiện/nguyên nhân/ý nghĩa DỄ NHẦM LẪN nhưng CÓ THẬT và bạn chắc chắn đúng - không được bịa ra một sự kiện/chi tiết không có thật chỉ để tạo độ khó."
      : '';

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
- QUAN TRỌNG NHẤT: Mảng "questions" PHẢI có ĐÚNG CHÍNH XÁC {$count} phần tử, không được thiếu, không được thừa. Đây là yêu cầu bắt buộc quan trọng nhất - hãy đếm lại số phần tử trong mảng trước khi trả về để đảm bảo đúng {$count} câu. id đánh số liên tục từ 1 đến {$count}, không bỏ số nào.
{$topicFocusRule}
{$curriculumRule}
{$topicFieldRule}
{$distractorRule}
- "options" luôn có đúng 4 phần tử, mỗi phần tử bắt đầu bằng "A. ", "B. ", "C. " hoặc "D. ".
- "answer" chỉ được là một trong các ký tự: "A", "B", "C", "D".
- Chỉ trả về JSON thuần túy, không dùng thẻ markdown (```), không viết lời mở đầu hay kết luận.

QUY TẮC BẮT BUỘC VỀ ĐỘ CHÍNH XÁC (quan trọng hơn tất cả các quy tắc khác):
- TUYỆT ĐỐI KHÔNG được bịa đặt số liệu, sự kiện, mốc thời gian, tên riêng, công thức hay trích dẫn. Chỉ sử dụng kiến thức bạn CHẮC CHẮN 100% là đúng và được công nhận rộng rãi trong sách giáo khoa/kiến thức phổ thông chuẩn. Nếu không chắc chắn về một sự kiện/số liệu cụ thể, hãy chọn hỏi một nội dung khác mà bạn chắc chắn hơn thay vì đoán bừa.
- Mỗi câu hỏi CHỈ được có DUY NHẤT MỘT đáp án đúng - điều này không bao giờ được thay đổi dù ở mức độ nào. Xét theo kiến thức chuẩn, chính xác, 3 phương án còn lại phải sai dứt khoát, có căn cứ rõ ràng để loại trừ khi phân tích kỹ (xem thêm quy tắc về độ "gần đúng" của phương án nhiễu theo từng mức độ bên dưới) - nhưng KHÔNG được để trường hợp 2 phương án cùng có thể coi là đúng, hoặc đáp án đúng phụ thuộc vào cách diễn giải/quan điểm cá nhân.
- Được phép đặt câu hỏi mang tính đánh đố về mặt LOGIC (đặc biệt ở mức khó), nhưng tuyệt đối không được TỰ ĐOÁN MÒ khi chính bạn không chắc chắn về sự kiện/kiến thức - nếu không chắc, hãy đổi sang nội dung/cách hỏi khác mà bạn chắc chắn hơn, còn hơn hỏi kiến thức "khó nhớ chính xác" rồi trả lời sai.
- TRƯỚC KHI đưa một câu hỏi vào kết quả cuối cùng, hãy tự kiểm tra lại trong đầu: (1) đáp án bạn chọn có thực sự đúng theo kiến thức chuẩn không, (2) ba phương án nhiễu còn lại có thực sự sai không, (3) câu hỏi có thuộc đúng chủ đề/lớp/mức độ yêu cầu không. Nếu có bất kỳ nghi ngờ nào ở một trong ba điểm trên, hãy thay câu hỏi đó bằng một câu khác chắc chắn hơn - không được giữ lại câu hỏi mà bạn không chắc chắn chỉ để đủ số lượng.{$historyRule}
PROMPT;
  }

  /**
   * Heuristic: does this topic string appear to be about learning/using a
   * (non-Vietnamese) language, as opposed to some other subject that merely
   * has a foreign-sounding name?
   */
  private function looksLikeLanguageTopic(string $topic): bool
  {
    $lower = mb_strtolower($topic);
    if (str_contains($lower, 'tiếng việt')) {
      return false;
    }

    $keywords = [
      'tiếng', 'ngôn ngữ', 'ngoại ngữ', 'ngữ pháp', 'từ vựng', 'phát âm',
      'language', 'grammar', 'vocabulary',
      'english', 'russian', 'french', 'japanese', 'chinese', 'korean',
      'german', 'spanish', 'anh văn',
    ];
    foreach ($keywords as $keyword) {
      if (str_contains($lower, $keyword)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Heuristic: is this topic History (or history-adjacent, e.g. a custom
   * topic like "Lịch sử Việt Nam thời kỳ kháng chiến")? Used to attach
   * extra-strict anti-hallucination rules, since dates/figures/battles are
   * where the model is most prone to confidently inventing precision.
   */
  private function looksLikeHistoryTopic(string $topic): bool
  {
    $lower = mb_strtolower($topic);
    $keywords = ['lịch sử', 'history'];
    foreach ($keywords as $keyword) {
      if (str_contains($lower, $keyword)) {
        return true;
      }
    }

    return false;
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
