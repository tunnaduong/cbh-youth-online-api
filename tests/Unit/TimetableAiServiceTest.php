<?php

namespace Tests\Unit;

use App\Http\Controllers\TimetableController;
use App\Services\TimetableAiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimetableAiServiceTest extends TestCase
{
  private const USER_TEXT = "Sáng tiết 1 từ 7h30 đến 8h15\nRa chs 15p\nSáng tiết 2 8h30 đến 9h15\nRa chs 10p\nTiết 3 9h25 dênd 10h10\nRa chs 10p\nTiết 4 10h20 đến 11h05\nChiều tiết 1 13h45 đến 14h30\nTiết 2 14h35 đến 15h20\nTiết 3 15h25 đến 16h10";

  // What a model typically answers for USER_TEXT: fenced, afternoon in 12-hour form.
  private function modelReply(): string
  {
    $day = [
      ['type' => 'hoc', 'start' => '07:30', 'end' => '08:15', 'subject' => 'Sáng - Tiết 1'],
      ['type' => 'nghi', 'start' => '08:15', 'end' => '08:30', 'label' => 'Ra chơi'],
      ['type' => 'hoc', 'start' => '08:30', 'end' => '09:15', 'subject' => 'Sáng - Tiết 2'],
      ['type' => 'nghi', 'start' => '09:15', 'end' => '09:25', 'label' => 'Ra chơi'],
      ['type' => 'hoc', 'start' => '09:25', 'end' => '10:10', 'subject' => 'Sáng - Tiết 3'],
      ['type' => 'nghi', 'start' => '10:10', 'end' => '10:20', 'label' => 'Ra chơi'],
      ['type' => 'hoc', 'start' => '10:20', 'end' => '11:05', 'subject' => 'Sáng - Tiết 4'],
      ['type' => 'hoc', 'start' => '01:45', 'end' => '02:30', 'subject' => 'Chiều - Tiết 1'],
      ['type' => 'hoc', 'start' => '14:35', 'end' => '15:20', 'subject' => 'Chiều - Tiết 2'],
      ['type' => 'hoc', 'start' => '15:25', 'end' => '04:10', 'subject' => 'Chiều - Tiết 3'],
    ];
    $schedule = ['T2' => $day, 'T3' => $day, 'T4' => $day, 'T5' => $day, 'T6' => $day, 'T7' => $day, 'CN' => []];

    return "```json\n" . json_encode(['schedule' => $schedule, 'note' => 'Đã áp dụng cho Thứ 2 đến Thứ 7.'], JSON_UNESCAPED_UNICODE) . "\n```";
  }

  public function test_text_import_through_gemini_fallback(): void
  {
    config(['services.image_moderation.key' => null, 'services.gemini.keys' => ['fake-key']]);
    Http::fake([
      'generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => $this->modelReply()]]]]],
      ]),
    ]);

    $result = app(TimetableAiService::class)->parse(self::USER_TEXT, null, null);
    $normalize = new \ReflectionMethod(TimetableController::class, 'normalize');
    $normalize->setAccessible(true);
    $data = $normalize->invoke(new TimetableController(), $result['schedule']);

    $this->assertSame('Đã áp dụng cho Thứ 2 đến Thứ 7.', $result['note']);
    $this->assertCount(10, $data['T2']);
    $this->assertSame([], $data['CN']);
    $afternoon = array_values(array_filter($data['T5'], fn($b) => $b['start'] >= '12:00'));
    $this->assertSame(['13:45', '14:35', '15:25'], array_column($afternoon, 'start'));
    $this->assertSame('16:10', $afternoon[2]['end']);
    $this->assertSame('nghi', $data['T2'][1]['type']);
    $this->assertSame('08:30', $data['T2'][1]['end']);
    $this->assertSame(['09:15', '09:25'], [$data['T2'][3]['start'], $data['T2'][3]['end']]);
    $this->assertSame('09:25', $data['T2'][4]['start']);

    Http::assertSent(fn($req) => str_contains(json_encode($req->data(), JSON_UNESCAPED_UNICODE), 'Ra chs 15p'));
  }

  public function test_photo_goes_to_the_vision_endpoint_first(): void
  {
    config(['services.image_moderation.key' => 'k', 'services.image_moderation.url' => 'https://vision.test/v1/chat', 'services.gemini.keys' => []]);
    Http::fake(['vision.test/*' => Http::response(['choices' => [['message' => ['content' => $this->modelReply()]]]])]);

    $png = \Illuminate\Http\UploadedFile::fake()->image('tkb.png', 40, 40);
    $result = app(TimetableAiService::class)->parse('', $png, ['T2' => [['type' => 'hoc', 'start' => '07:00', 'end' => '07:45', 'subject' => 'Toán']]]);

    $this->assertNotEmpty($result['schedule']['T2']);
    Http::assertSent(function ($req) {
      $content = $req->data()['messages'][1]['content'];

      return $content[1]['type'] === 'image_url'
        && str_starts_with($content[1]['image_url']['url'], 'data:image/png;base64,')
        && str_contains($content[0]['text'], 'Lịch hiện tại');
    });
  }

  public function test_no_model_configured(): void
  {
    config(['services.image_moderation.key' => null, 'services.gemini.keys' => []]);
    $this->expectExceptionMessage('no_model');
    app(TimetableAiService::class)->parse('Tiết 1 7h-7h45', null, null);
  }
}
