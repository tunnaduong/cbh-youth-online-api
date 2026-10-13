<?php

namespace App\Services;

use App\Models\Topic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Makes a cover picture for a post that has no photo of its own, so the home
 * page's featured cards and other post lists have something to show:
 *  - a post with a video: the video's first frame (ffmpeg), cropped to 16:10;
 *  - a text post: the title and the start of the markdown body, set on a white
 *    "page" over the brand green. Pure GD + FreeType, no browser.
 * A video whose frame cannot be read falls back to the text picture.
 *
 * The picture is stored on the public disk (`covers/`) and its path in
 * `cyo_topics.cover_image`; clients read it as `cover_url`. It is never added to
 * the post's own images, so the post page and its "Hình ảnh" label are unchanged.
 */
class TopicCoverService
{
  const WIDTH = 1200;
  const HEIGHT = 750; // 16:10, the aspect ratio of the featured cards
  const DIRECTORY = 'covers';

  /** Posts with their own photo need no generated cover. */
  public static function needsCover(Topic $topic): bool
  {
    return empty($topic->cdn_image_id)
      && trim(($topic->title ?? '') . ($topic->description ?? '')) !== '';
  }

  /**
   * Draw and store the cover of a topic, replacing the previous one.
   * Returns the stored path, or null when the topic has its own media / text
   * (any stale cover is then removed) or drawing failed.
   */
  public function generate(Topic $topic): ?string
  {
    if (!self::needsCover($topic)) {
      $this->forget($topic);
      return null;
    }

    $jpeg = $this->videoFrame($topic)
      ?? $this->render((string) $topic->title, (string) $topic->description);
    if ($jpeg === null) {
      return null;
    }

    $path = self::DIRECTORY . '/topic-' . $topic->id . '-' . substr(md5($jpeg), 0, 10) . '.jpg';
    Storage::disk('public')->put($path, $jpeg);

    $old = $topic->cover_image;
    $topic->forceFill(['cover_image' => $path])->saveQuietly();
    if ($old && $old !== $path) {
      Storage::disk('public')->delete($old);
    }

    return $path;
  }

  /** First frame of the topic's first video as a 1200x750 JPEG, or null. */
  private function videoFrame(Topic $topic): ?string
  {
    $video = $topic->getVideos()->first();
    if (!$video) {
      return null;
    }

    $input = Storage::disk('public')->path($video->file_path);
    if (!is_file($input)) {
      return null;
    }

    $base = tempnam(sys_get_temp_dir(), 'cover');
    $output = $base . '.jpg';
    try {
      $process = new Process([
        env('FFMPEG_BINARY', 'ffmpeg'),
        '-y', '-i', $input,
        '-frames:v', '1',
        '-vf', sprintf('scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d', self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT),
        '-q:v', '3',
        $output,
      ]);
      $process->setTimeout(60);
      $process->run();

      if (!$process->isSuccessful() || !is_file($output) || filesize($output) === 0) {
        Log::warning('TopicCoverService: ffmpeg could not read a frame', ['topic' => $topic->id]);
        return null;
      }
      return file_get_contents($output) ?: null;
    } catch (\Throwable $e) {
      Log::warning('TopicCoverService: video frame failed', ['topic' => $topic->id, 'error' => $e->getMessage()]);
      return null;
    } finally {
      @unlink($output);
      @unlink($base);
    }
  }

  /** Remove the topic's cover file and column. */
  public function forget(Topic $topic): void
  {
    if ($topic->cover_image) {
      Storage::disk('public')->delete($topic->cover_image);
      $topic->forceFill(['cover_image' => null])->saveQuietly();
    }
  }

  /** The cover as JPEG bytes, or null when GD / the fonts are not available. */
  public function render(string $title, string $markdown): ?string
  {
    if (!function_exists('imagettftext') || !is_file($this->font('bold'))) {
      Log::warning('TopicCoverService: GD with FreeType or the Inter font is missing.');
      return null;
    }

    $w = self::WIDTH;
    $h = self::HEIGHT;
    $img = imagecreatetruecolor($w, $h);
    imageantialias($img, true);

    // Background: the same soft green as the placeholder in the web app.
    $this->verticalGradient($img, 0, 0, $w, $h, [0xE5, 0xF5, 0xE2], [0xC9, 0xEB, 0xC3]);

    // The page: starts below the top edge and runs off the bottom, like a
    // screenshot of a document.
    $cardX = 90;
    $cardY = 64;
    $cardW = $w - 2 * $cardX;
    $radius = 32;
    $white = imagecolorallocate($img, 255, 255, 255);
    $this->roundedTopRect($img, $cardX, $cardY, $cardX + $cardW, $h, $radius, $white);

    $pad = 56;
    $textX = $cardX + $pad;
    $textW = $cardW - 2 * $pad;
    $y = $cardY + $pad;
    $bottom = $h - 30;

    // Title
    $titleColor = imagecolorallocate($img, 0x11, 0x18, 0x27);
    $titleLines = $this->wrap($this->clean($title), $this->font('bold'), 46, $textW, 3);
    foreach ($titleLines as $line) {
      $y += 46 + 6;
      imagettftext($img, 46, 0, $textX, $y, $titleColor, $this->font('bold'), $line);
      $y += 14;
    }

    // Divider
    $y += 6;
    imagefilledrectangle($img, $textX, $y, $textX + 96, $y + 5, imagecolorallocate($img, 0x31, 0x95, 0x27));
    $y += 34;

    // Markdown body
    $body = imagecolorallocate($img, 0x4B, 0x55, 0x63);
    $heading = imagecolorallocate($img, 0x1F, 0x29, 0x37);
    $quoteBar = imagecolorallocate($img, 0xC7, 0xF0, 0xC2);
    $green = imagecolorallocate($img, 0x31, 0x95, 0x27);

    foreach ($this->blocks($markdown) as $block) {
      if ($y > $bottom) {
        break;
      }
      [$type, $text] = $block;
      $size = 27;
      $font = $this->font('regular');
      $color = $body;
      $indent = 0;
      $lead = null;

      if ($type === 'heading') {
        $size = 31;
        $font = $this->font('semibold');
        $color = $heading;
        $y += 10;
      } elseif ($type === 'bullet') {
        $indent = 34;
        $lead = '•';
      } elseif ($type === 'number') {
        $indent = 42;
        $lead = $block[2] . '.';
      } elseif ($type === 'quote') {
        $indent = 28;
      }

      $lines = $this->wrap($text, $font, $size, $textW - $indent, $type === 'heading' ? 2 : 4);
      $startY = $y;
      foreach ($lines as $i => $line) {
        $y += (int) round($size * 1.5);
        if ($lead !== null && $i === 0) {
          imagettftext($img, $size, 0, $textX + ($type === 'bullet' ? 6 : 0), $y, $green, $this->font('semibold'), $lead);
        }
        imagettftext($img, $size, 0, $textX + $indent, $y, $color, $font, $line);
        if ($y > $bottom + 40) {
          break;
        }
      }
      if ($type === 'quote') {
        imagefilledrectangle($img, $textX, $startY + 8, $textX + 5, $y + 6, $quoteBar);
      }
      $y += $type === 'heading' ? 12 : 18;
    }

    // Fade the text out at the bottom, so it reads as "continues".
    $this->fadeBottom($img, $cardX, $h - 190, $cardX + $cardW, $h, [255, 255, 255]);

    ob_start();
    imagejpeg($img, null, 88);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return $bytes === false || $bytes === '' ? null : $bytes;
  }

  private function font(string $weight): string
  {
    $dir = dirname(__DIR__, 2) . '/resources/fonts/Inter/';
    return $dir . match ($weight) {
      'bold' => 'Inter-Bold.otf',
      'semibold' => 'Inter-SemiBold.otf',
      default => 'Inter-Regular.otf',
    };
  }

  /** Drop what the font cannot draw (emoji and friends) and tidy whitespace. */
  private function clean(string $text): string
  {
    $text = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2190}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200B}-\x{200F}\x{E000}-\x{F8FF}]/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
  }

  /**
   * Markdown to a short list of blocks: [type, text(, number)] with type
   * heading | paragraph | bullet | number | quote. Inline marks are removed.
   */
  private function blocks(string $markdown): array
  {
    $markdown = preg_replace('/```.*?```/s', '', $markdown) ?? $markdown;      // code fences
    $markdown = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $markdown) ?? $markdown; // images
    $markdown = preg_replace('/<[^>]+>/', '', $markdown) ?? $markdown;          // raw html
    $blocks = [];

    foreach (preg_split('/\R/u', $markdown) as $raw) {
      $line = trim($raw);
      if ($line === '' || preg_match('/^([-*_]\s*){3,}$/', $line) || str_starts_with($line, '|')) {
        continue;
      }

      if (preg_match('/^#{1,6}\s+(.*)$/u', $line, $m)) {
        $type = 'heading';
        $text = $m[1];
        $extra = null;
      } elseif (preg_match('/^[-*+]\s+(.*)$/u', $line, $m)) {
        $type = 'bullet';
        $text = $m[1];
        $extra = null;
      } elseif (preg_match('/^(\d{1,2})[.)]\s+(.*)$/u', $line, $m)) {
        $type = 'number';
        $text = $m[2];
        $extra = $m[1];
      } elseif (preg_match('/^>\s?(.*)$/u', $line, $m)) {
        $type = 'quote';
        $text = $m[1];
        $extra = null;
      } else {
        $type = 'paragraph';
        $text = $line;
        $extra = null;
      }

      $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text; // [text](url)
      $text = preg_replace('/https?:\/\/\S+/u', '', $text) ?? $text;
      $text = preg_replace('/(\*\*|__|\*|_|~~|`)/u', '', $text) ?? $text;
      $text = $this->clean($text);
      if ($text === '') {
        continue;
      }

      $blocks[] = [$type, $text, $extra];
      if (count($blocks) >= 14) {
        break;
      }
    }

    return $blocks;
  }

  /** Word-wrap to a pixel width; the last allowed line gets an ellipsis. */
  private function wrap(string $text, string $font, int $size, int $width, int $maxLines): array
  {
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $lines = [];
    $current = '';

    foreach ($words as $word) {
      $try = $current === '' ? $word : $current . ' ' . $word;
      if ($this->textWidth($try, $font, $size) <= $width) {
        $current = $try;
        continue;
      }
      if ($current !== '') {
        $lines[] = $current;
      }
      $current = $word;
      // A single word wider than the line (long URL, no spaces): cut it.
      while ($this->textWidth($current, $font, $size) > $width && mb_strlen($current) > 1) {
        $cut = mb_strlen($current) - 1;
        while ($cut > 1 && $this->textWidth(mb_substr($current, 0, $cut), $font, $size) > $width) {
          $cut--;
        }
        $lines[] = mb_substr($current, 0, $cut);
        $current = mb_substr($current, $cut);
      }
    }
    if ($current !== '') {
      $lines[] = $current;
    }

    if (count($lines) > $maxLines) {
      $lines = array_slice($lines, 0, $maxLines);
      $last = rtrim($lines[$maxLines - 1], " ,.;:-");
      while ($last !== '' && $this->textWidth($last . '…', $font, $size) > $width) {
        $last = rtrim(mb_substr($last, 0, -1));
      }
      $lines[$maxLines - 1] = $last . '…';
    }

    return $lines;
  }

  private function textWidth(string $text, string $font, int $size): int
  {
    $box = imagettfbbox($size, 0, $font, $text);
    return $box ? abs($box[2] - $box[0]) : 0;
  }

  private function verticalGradient($img, int $x1, int $y1, int $x2, int $y2, array $from, array $to): void
  {
    $span = max(1, $y2 - $y1 - 1);
    for ($y = $y1; $y < $y2; $y++) {
      $t = ($y - $y1) / $span;
      $c = imagecolorallocate(
        $img,
        (int) round($from[0] + ($to[0] - $from[0]) * $t),
        (int) round($from[1] + ($to[1] - $from[1]) * $t),
        (int) round($from[2] + ($to[2] - $from[2]) * $t),
      );
      imageline($img, $x1, $y, $x2 - 1, $y, $c);
    }
  }

  /** Rectangle with rounded top corners (the bottom runs off the canvas). */
  private function roundedTopRect($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
  {
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2, $color);
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y1 + $r, $color);
    imagefilledellipse($img, $x1 + $r, $y1 + $r, 2 * $r, 2 * $r, $color);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, 2 * $r, 2 * $r, $color);
  }

  private function fadeBottom($img, int $x1, int $y1, int $x2, int $y2, array $rgb): void
  {
    $span = max(1, $y2 - $y1);
    for ($y = $y1; $y < $y2; $y++) {
      $alpha = (int) round(127 - 127 * (($y - $y1) / $span) ** 1.4); // 127 = clear, 0 = solid
      $c = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha)));
      imageline($img, $x1, $y, $x2 - 1, $y, $c);
    }
  }
}
