<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Nhập bằng AI" for the class timetable: turns free text ("Sáng tiết 1 từ 7h30
 * đến 8h15, ra chơi 15p...") and/or a photo of a printed timetable into the
 * vaolop.app JSON the rest of TimetableController works with.
 *
 * The model only drafts the JSON; TimetableController::normalize() still checks
 * every block, and fixAfternoon() below corrects the one mistake models keep
 * making (afternoon times written as 1:45 instead of 13:45). The result is
 * shown to the user to review before they save it.
 *
 * Uses the vision endpoint already configured for photo moderation
 * (services.image_moderation: any OpenAI-compatible chat API), then the
 * Gemini keys (services.gemini.keys) directly when that is missing or fails.
 */
class TimetableAiService
{
  const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
  const TIMEOUT_SECONDS = 60;

  const SYSTEM_PROMPT = <<<'PROMPT'
Bạn chuyển thời khóa biểu của học sinh Việt Nam (đoạn chữ người dùng gõ, hoặc ảnh chụp thời khóa biểu) thành JSON.

Chỉ trả về một đối tượng JSON, không giải thích, không markdown:
{"schedule": {"T2": [...], "T3": [...], "T4": [...], "T5": [...], "T6": [...], "T7": [...], "CN": [...]}, "note": "..."}

Mỗi phần tử trong một ngày là một khối, theo thứ tự thời gian:
- Tiết học: {"type": "hoc", "start": "HH:MM", "end": "HH:MM", "subject": "Tên môn"}
- Giờ nghỉ: {"type": "nghi", "start": "HH:MM", "end": "HH:MM", "label": "Ra chơi"}

Quy tắc:
1. Giờ dạng 24 giờ, luôn 2 chữ số ("07:30", "13:45"). "7h30" = 07:30. Buổi chiều luôn từ 12:00 trở đi: "chiều 1h45" hoặc "chiều tiết 1 13h45" đều là 13:45.
2. "Ra chơi 15p" (hay "ra chs", "nghỉ 15 phút"...) ghi sau một tiết = khối nghỉ bắt đầu đúng lúc tiết đó kết thúc, dài bằng số phút ghi. Nếu số phút không khớp với giờ bắt đầu tiết sau, cho giờ nghỉ kết thúc đúng lúc tiết sau bắt đầu. Không tự thêm giờ nghỉ khi người dùng không nhắc tới, và không thêm khối nghỉ trưa giữa buổi sáng và buổi chiều.
3. Tên môn: lấy đúng như trong chữ / ảnh (sửa lỗi chính tả rõ ràng, viết hoa chữ đầu). Nếu chỉ có khung giờ mà không có tên môn: nếu có "Lịch hiện tại" bên dưới thì lấy tên môn của tiết cùng ngày, cùng buổi, cùng số thứ tự trong lịch đó; nếu không có thì đặt "Sáng - Tiết N" hoặc "Chiều - Tiết N".
4. Ngày: nếu chữ / ảnh ghi rõ thứ thì xếp đúng thứ. Nếu không ghi thứ (chỉ là khung giờ chung), áp dụng cho mọi ngày đang có tiết học trong "Lịch hiện tại"; nếu không có lịch hiện tại thì áp dụng cho Thứ 2 đến Thứ 7. Ngày không có gì thì để mảng rỗng.
5. Chấp nhận lỗi gõ ("dênd" = "đến", "chs" = "chơi", "p" = "phút").
6. "note": một câu tiếng Việt ngắn nói bạn đã giả định gì (ví dụ đã áp dụng cho những thứ nào, tên môn lấy từ đâu). Nếu không đọc được thời khóa biểu nào thì trả "schedule": {} và nói lý do trong "note".
PROMPT;

  /**
   * @return array{schedule: array, note: string}
   * @throws \RuntimeException when no model could be reached or the reply is not JSON
   */
  public function parse(?string $text, ?UploadedFile $image, ?array $current = null): array
  {
    $userText = "Chuyển thời khóa biểu sau thành JSON.";
    if ($text !== null && trim($text) !== '') {
      $userText .= "\n\nNội dung người dùng gõ:\n" . trim($text);
    }
    if ($image) {
      $userText .= "\n\n(Thời khóa biểu nằm trong ảnh đính kèm.)";
    }
    if ($current && $this->hasLessons($current)) {
      $userText .= "\n\nLịch hiện tại (để lấy tên môn / ngày học khi nội dung trên không ghi):\n"
        . json_encode($current, JSON_UNESCAPED_UNICODE);
    }

    $dataUri = $image ? 'data:' . $image->getMimeType() . ';base64,' . base64_encode($image->get()) : null;

    $raw = $this->askOpenAiCompatible($userText, $dataUri) ?? $this->askGemini($userText, $image);
    if ($raw === null) {
      throw new \RuntimeException('no_model');
    }

    $json = $this->decode($raw);
    if (!is_array($json)) {
      throw new \RuntimeException('bad_json');
    }

    $schedule = $json['schedule'] ?? $json;
    if (!is_array($schedule)) {
      $schedule = [];
    }

    return [
      'schedule' => $this->fixAfternoon($schedule),
      'note'     => is_string($json['note'] ?? null) ? mb_substr(trim($json['note']), 0, 300) : '',
    ];
  }

  private function hasLessons(array $schedule): bool
  {
    foreach ($schedule as $blocks) {
      if (is_array($blocks) && count($blocks) > 0) {
        return true;
      }
    }

    return false;
  }

  private function askOpenAiCompatible(string $userText, ?string $dataUri): ?string
  {
    $config = config('services.image_moderation');
    if (empty($config['key'])) {
      return null;
    }

    $content = [['type' => 'text', 'text' => $userText]];
    if ($dataUri) {
      $content[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
    }

    try {
      $response = Http::withToken($config['key'])
        ->timeout(self::TIMEOUT_SECONDS)
        ->post($config['url'], [
          'model'           => $config['model'],
          'messages'        => [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $content],
          ],
          'temperature'     => 0,
          'response_format' => ['type' => 'json_object'],
        ]);
      $text = $response->json('choices.0.message.content');
      if ($response->successful() && is_string($text) && trim($text) !== '') {
        return $text;
      }
      Log::warning('TimetableAi: vision endpoint returned ' . $response->status());
    } catch (\Throwable $e) {
      Log::warning('TimetableAi: vision endpoint failed: ' . $e->getMessage());
    }

    return null;
  }

  private function askGemini(string $userText, ?UploadedFile $image): ?string
  {
    $keys = config('services.gemini.keys', []);
    if (empty($keys)) {
      return null;
    }
    shuffle($keys);

    $parts = [['text' => $userText]];
    if ($image) {
      $parts[] = ['inline_data' => ['mime_type' => $image->getMimeType(), 'data' => base64_encode($image->get())]];
    }

    foreach ($keys as $key) {
      try {
        $response = Http::withHeaders(['x-goog-api-key' => $key])
          ->timeout(self::TIMEOUT_SECONDS)
          ->post(self::GEMINI_URL, [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents'          => [['role' => 'user', 'parts' => $parts]],
            'generationConfig'  => ['temperature' => 0, 'responseMimeType' => 'application/json'],
          ]);
        $text = $response->json('candidates.0.content.parts.0.text');
        if ($response->successful() && is_string($text) && trim($text) !== '') {
          return $text;
        }
        Log::warning('TimetableAi: Gemini returned ' . $response->status());
      } catch (\Throwable $e) {
        Log::warning('TimetableAi: Gemini failed: ' . $e->getMessage());
      }
    }

    return null;
  }

  // Some models wrap the JSON in a code fence or add a sentence around it.
  private function decode(string $raw): ?array
  {
    $raw = trim($raw);
    $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
    $data = json_decode($raw, true);
    if (is_array($data)) {
      return $data;
    }
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start !== false && $end > $start) {
      $data = json_decode(substr($raw, $start, $end - $start + 1), true);
    }

    return is_array($data) ? $data : null;
  }

  /**
   * Lessons never start before 6 in the morning: an hour of 1-5 is an
   * afternoon time the model wrote in 12-hour form ("01:45" → "13:45"). A
   * block whose end comes out before its start gets the same fix on the end.
   */
  public function fixAfternoon(array $schedule): array
  {
    $shift = function ($time) {
      if (!is_string($time) || !preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $m)) {
        return $time;
      }
      $h = (int) $m[1];
      if ($h >= 1 && $h <= 5) {
        $h += 12;
      }

      return sprintf('%02d:%s', $h, $m[2]);
    };

    foreach ($schedule as $day => $blocks) {
      if (!is_array($blocks)) {
        continue;
      }
      foreach ($blocks as $i => $block) {
        if (!is_array($block)) {
          continue;
        }
        $start = $shift($block['start'] ?? null);
        $end = $shift($block['end'] ?? null);
        if (is_string($start) && is_string($end) && strcmp($end, $start) < 0 && preg_match('/^(\d{2}):(\d{2})$/', $end, $m) && (int) $m[1] < 12) {
          $end = sprintf('%02d:%s', (int) $m[1] + 12, $m[2]);
        }
        $schedule[$day][$i]['start'] = $start;
        $schedule[$day][$i]['end'] = $end;
      }
    }

    return $schedule;
  }
}
