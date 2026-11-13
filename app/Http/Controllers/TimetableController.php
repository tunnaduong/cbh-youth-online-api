<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class timetable ("Thời khóa biểu"), modelled on vaolop.app: per weekday a
 * list of blocks, each a lesson ({type: "hoc", start, end, subject}) or a
 * break ({type: "nghi", start, end, label}). Times are "HH:MM" in local time.
 * The clients work out the current / next period themselves; the API only
 * stores the schedule so the web and the app share it. Same JSON shape as
 * vaolop.app, so a schedule exported there can be imported here.
 */
class TimetableController extends Controller
{
  const DAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
  const MAX_BLOCKS_PER_DAY = 30;

  private function demo(): array
  {
    static $demo = null;

    return $demo ??= json_decode(file_get_contents(resource_path('data/timetable_demo.json')), true);
  }

  // GET /v1.0/timetable/demo
  public function demoSchedule()
  {
    return response()->json(['data' => $this->demo()]);
  }

  // GET /v1.0/timetable → { data, is_demo, updated_at }
  public function show(Request $request)
  {
    $timetable = Timetable::where('user_id', $request->user()->id)->first();

    return response()->json([
      'data'       => $timetable?->data ?? $this->demo(),
      'is_demo'    => !$timetable,
      'updated_at' => $timetable?->updated_at?->toIso8601String(),
    ]);
  }

  // PUT /v1.0/timetable { data }
  public function update(Request $request)
  {
    $data = $this->normalize($request->input('data'));

    $timetable = Timetable::updateOrCreate(
      ['user_id' => $request->user()->id],
      ['data' => $data]
    );

    return response()->json([
      'data'       => $timetable->data,
      'is_demo'    => false,
      'updated_at' => $timetable->updated_at->toIso8601String(),
    ]);
  }

  // DELETE /v1.0/timetable → back to the demo schedule
  public function destroy(Request $request)
  {
    Timetable::where('user_id', $request->user()->id)->delete();

    return response()->json(['data' => $this->demo(), 'is_demo' => true, 'updated_at' => null]);
  }

  /**
   * Check and clean a schedule: known days only, valid times with end after
   * start, trimmed names, blocks sorted by start time. Any problem is a 422
   * naming the day and row, in Vietnamese.
   */
  private function normalize($data): array
  {
    $fail = fn(string $msg) => throw ValidationException::withMessages(['data' => $msg]);

    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
      $fail('Thời khóa biểu không hợp lệ.');
    }

    $out = [];
    foreach (self::DAYS as $day) {
      $blocks = $data[$day] ?? [];
      if (!is_array($blocks) || !array_is_list($blocks)) {
        $fail("Lịch {$this->dayName($day)} không hợp lệ.");
      }
      if (count($blocks) > self::MAX_BLOCKS_PER_DAY) {
        $fail("{$this->dayName($day)} có quá nhiều tiết (tối đa " . self::MAX_BLOCKS_PER_DAY . ').');
      }

      $clean = [];
      foreach ($blocks as $i => $block) {
        $row = $i + 1;
        $where = "{$this->dayName($day)}, dòng {$row}";
        if (!is_array($block)) {
          $fail("{$where}: không hợp lệ.");
        }
        $type = ($block['type'] ?? 'hoc') === 'nghi' ? 'nghi' : 'hoc';
        $start = $this->time($block['start'] ?? null);
        $end = $this->time($block['end'] ?? null);
        if (!$start || !$end) {
          $fail("{$where}: giờ phải có dạng HH:MM.");
        }
        if ($end <= $start) {
          $fail("{$where}: giờ kết thúc phải sau giờ bắt đầu.");
        }

        if ($type === 'nghi') {
          $label = trim((string) ($block['label'] ?? '')) ?: 'Ra chơi';
          $clean[] = ['type' => 'nghi', 'start' => $start, 'end' => $end, 'label' => mb_substr($label, 0, 60)];
        } else {
          $subject = trim((string) ($block['subject'] ?? ''));
          if ($subject === '') {
            $fail("{$where}: chưa nhập tên môn.");
          }
          $clean[] = ['type' => 'hoc', 'start' => $start, 'end' => $end, 'subject' => mb_substr($subject, 0, 60)];
        }
      }

      usort($clean, fn($a, $b) => strcmp($a['start'], $b['start']));
      $out[$day] = $clean;
    }

    return $out;
  }

  // "7:5" / "07:05" / "07:05:00" → "07:05"; anything else → null.
  private function time($value): ?string
  {
    if (!is_string($value) || !preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', trim($value), $m)) {
      return null;
    }
    $h = (int) $m[1];
    $min = (int) $m[2];
    if ($h > 23 || $min > 59) {
      return null;
    }

    return sprintf('%02d:%02d', $h, $min);
  }

  private function dayName(string $day): string
  {
    return $day === 'CN' ? 'Chủ nhật' : 'Thứ ' . substr($day, 1);
  }
}
