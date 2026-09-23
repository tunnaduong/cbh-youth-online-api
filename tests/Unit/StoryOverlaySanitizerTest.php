<?php

namespace Tests\Unit;

use App\Http\Controllers\StoryController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The story editor posts overlays and a soundtrack as JSON; both are rendered
 * straight back to other users, so the sanitizing is worth pinning down.
 */
class StoryOverlaySanitizerTest extends TestCase
{
    private function call(string $method, $argument)
    {
        $reflection = new ReflectionMethod(StoryController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new StoryController(), $argument);
    }

    public function test_it_keeps_valid_overlay_items_and_normalizes_transforms(): void
    {
        $result = $this->call('sanitizeOverlays', json_encode([
            'flattened' => true,
            'filter' => 'warm',
            'items' => [
                [
                    'type' => 'text',
                    'x' => 0.1,
                    'y' => 0.4,
                    'scale' => 99,       // clamped
                    'rotation' => -12.5,
                    'width' => 0.86,
                    'text' => ' Xin chào ',
                    'color' => '#FF3B30',
                    'font' => 'modern',
                    'effect' => 'neon',
                    'align' => 'center',
                    'fontSize' => 0.09,
                ],
                ['type' => 'sticker', 'emoji' => '🔥'],
                ['type' => 'mention', 'username' => '@tunna', 'user_id' => '7'],
                ['type' => 'link', 'url' => 'https://chuyenbienhoa.com/abc', 'label' => 'Xem thêm'],
            ],
        ]));

        $this->assertTrue($result['flattened']);
        $this->assertSame('warm', $result['filter']);
        $this->assertCount(4, $result['items']);

        $text = $result['items'][0];
        $this->assertSame('Xin chào', $text['text']);
        $this->assertSame(8.0, $text['scale'], 'scale is clamped to the maximum');
        $this->assertSame(-12.5, $text['rotation']);

        $this->assertSame('tunna', $result['items'][2]['username'], 'the @ is stripped');
        $this->assertSame(7, $result['items'][2]['user_id']);
        $this->assertSame('https://chuyenbienhoa.com/abc', $result['items'][3]['url']);
    }

    public function test_it_drops_unusable_or_dangerous_items(): void
    {
        $result = $this->call('sanitizeOverlays', json_encode([
            'filter' => 'vivid',
            'items' => [
                ['type' => 'poll', 'question' => 'unsupported type'],
                ['type' => 'text', 'text' => '   '],
                ['type' => 'link', 'url' => 'javascript:alert(1)'],
                ['type' => 'link', 'url' => 'not a url'],
                ['type' => 'sticker', 'emoji' => '✨'],
            ],
        ]));

        $this->assertCount(1, $result['items']);
        $this->assertSame('sticker', $result['items'][0]['type']);
    }

    public function test_it_falls_back_to_defaults_for_malformed_styling(): void
    {
        $result = $this->call('sanitizeOverlays', [
            'filter' => 'not a slug!',
            'items' => [
                [
                    'type' => 'text',
                    'text' => 'hi',
                    'color' => 'red',            // not a hex colour
                    'align' => 'justify',        // not an allowed alignment
                    'fontSize' => 'huge',
                ],
            ],
        ]);

        $this->assertSame('none', $result['filter']);
        $this->assertSame('#FFFFFF', $result['items'][0]['color']);
        $this->assertSame('center', $result['items'][0]['align']);
        $this->assertSame(0.08, $result['items'][0]['fontSize']);
    }

    public function test_it_returns_null_when_there_is_nothing_to_store(): void
    {
        $this->assertNull($this->call('sanitizeOverlays', null));
        $this->assertNull($this->call('sanitizeOverlays', 'not json'));
        $this->assertNull($this->call('sanitizeOverlays', json_encode(['filter' => 'none', 'items' => []])));
    }

    public function test_it_only_accepts_apple_preview_urls_for_music(): void
    {
        $accepted = $this->call('sanitizeMusic', json_encode([
            'track_id' => 12345,
            'title' => 'Bài hát',
            'artist' => 'Ca sĩ',
            'artwork_url' => 'https://is1-ssl.mzstatic.com/image/300x300bb.jpg',
            'preview_url' => 'https://audio-ssl.itunes.apple.com/preview.m4a',
            'start_ms' => 5000,
            'duration_ms' => 15000,
        ]));

        $this->assertSame('itunes', $accepted['provider']);
        $this->assertSame('12345', $accepted['track_id']);
        $this->assertSame(5000, $accepted['start_ms']);

        $this->assertNull($this->call('sanitizeMusic', json_encode([
            'preview_url' => 'https://evil.example.com/track.mp3',
        ])), 'a preview hosted anywhere else is rejected');

        $this->assertNull($this->call('sanitizeMusic', json_encode(['title' => 'no preview'])));
    }
}
