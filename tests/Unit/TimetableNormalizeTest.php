<?php

namespace Tests\Unit;

use App\Http\Controllers\TimetableController;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimetableNormalizeTest extends TestCase
{
  private function normalize($data)
  {
    $method = new \ReflectionMethod(TimetableController::class, 'normalize');
    $method->setAccessible(true);

    return $method->invoke(new TimetableController(), $data);
  }

  public function test_cleans_sorts_and_fills_every_day(): void
  {
    $out = $this->normalize([
      'T2' => [
        ['type' => 'hoc', 'start' => '8:5', 'end' => '08:50', 'subject' => '  Toán '],
        ['type' => 'nghi', 'start' => '07:45', 'end' => '08:00'],
        ['type' => 'hoc', 'start' => '07:00', 'end' => '07:45', 'subject' => 'Văn'],
      ],
    ]);

    $this->assertSame(['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'], array_keys($out));
    $this->assertSame(['07:00', '07:45', '08:05'], array_column($out['T2'], 'start'));
    $this->assertSame('Ra chơi', $out['T2'][1]['label']);
    $this->assertSame('Toán', $out['T2'][2]['subject']);
    $this->assertSame([], $out['CN']);
  }

  public function test_demo_file_is_valid(): void
  {
    $demo = json_decode(file_get_contents(resource_path('data/timetable_demo.json')), true);
    $this->assertCount(11, $this->normalize($demo)['T2']);
  }

  /** @dataProvider badSchedules */
  public function test_rejects_bad_input($data): void
  {
    $this->expectException(ValidationException::class);
    $this->normalize($data);
  }

  public static function badSchedules(): array
  {
    return [
      'not an object' => [['a', 'b']],
      'bad time' => [['T2' => [['type' => 'hoc', 'start' => '25:00', 'end' => '26:00', 'subject' => 'A']]]],
      'end before start' => [['T3' => [['type' => 'hoc', 'start' => '09:00', 'end' => '08:00', 'subject' => 'A']]]],
      'missing subject' => [['T4' => [['type' => 'hoc', 'start' => '07:00', 'end' => '07:45']]]],
      'too many' => [['T5' => array_fill(0, 31, ['type' => 'nghi', 'start' => '07:00', 'end' => '07:10'])]],
    ];
  }
}
