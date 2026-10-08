<?php

namespace Tests\Unit;

use App\Services\CustomFrameService;
use PHPUnit\Framework\TestCase;

class CustomFrameServiceTest extends TestCase
{
    private const SIZE = 320;

    /** Opacity of a ring between two radii (as parts of the image's width). */
    private function ring(float $inner, float $outer): callable
    {
        $centre = (self::SIZE - 1) / 2;

        return function (int $x, int $y) use ($inner, $outer, $centre) {
            $distance = hypot($x - $centre, $y - $centre) / self::SIZE;

            return $distance >= $inner && $distance <= $outer ? 1.0 : 0.0;
        };
    }

    public function test_an_avatar_frame_is_a_ring_around_a_clear_centre(): void
    {
        $this->assertNull(CustomFrameService::check('avatar', self::SIZE, $this->ring(0.36, 0.5)));
        // Overlapping the avatar's rim a little is fine.
        $this->assertNull(CustomFrameService::check('avatar', self::SIZE, $this->ring(0.31, 0.5)));
    }

    public function test_an_avatar_frame_may_not_cover_the_face(): void
    {
        $solid = fn() => 1.0;
        $this->assertStringContainsString('trong suốt', CustomFrameService::check('avatar', self::SIZE, $solid));
        $this->assertStringContainsString('trong suốt', CustomFrameService::check('avatar', self::SIZE, $this->ring(0.15, 0.5)));
        // A faint wash over the centre does not count as painted.
        $faint = fn(int $x, int $y) => max(0.05, $this->ring(0.36, 0.5)($x, $y));
        $this->assertNull(CustomFrameService::check('avatar', self::SIZE, $faint));
    }

    public function test_an_empty_image_is_not_a_frame(): void
    {
        $clear = fn() => 0.0;
        $this->assertStringContainsString('trống', CustomFrameService::check('avatar', self::SIZE, $clear));
        $this->assertStringContainsString('trống', CustomFrameService::check('profile', 480, $clear));
    }

    public function test_a_profile_frame_only_needs_its_border_band(): void
    {
        $border = fn(int $x, int $y) => min($x, $y, 479 - $x, 479 - $y) < 40 ? 1.0 : 0.0;
        $this->assertNull(CustomFrameService::check('profile', 480, $border));
        // The middle is never drawn, so painting it is harmless...
        $this->assertNull(CustomFrameService::check('profile', 480, fn() => 1.0));
        // ...and painting only the middle leaves nothing to draw.
        $middle = fn(int $x, int $y) => min($x, $y, 479 - $x, 479 - $y) >= 130 ? 1.0 : 0.0;
        $this->assertStringContainsString('trống', CustomFrameService::check('profile', 480, $middle));
    }

    public function test_the_rules_sent_to_clients_match_the_checks(): void
    {
        $rules = CustomFrameService::rules();

        $this->assertSame(['png', 'webp'], $rules['formats']);
        $this->assertSame(1.25, $rules['avatar']['scale']);
        $this->assertSame(0.25, $rules['profile']['slice']);
    }
}
