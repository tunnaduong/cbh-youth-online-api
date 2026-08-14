<?php

namespace App\Services;

/**
 * Shared "is this an intentionally-highlighted color, not just plain default
 * text" check, used by both QuizDocumentExtractionService (.docx font
 * color) and CustomQuizController's HTML-to-markup conversion (inline
 * `color` style/attribute) - creators can mark the correct option with
 * bold, underline, OR by coloring it differently from the other options
 * (any distinct color, not specifically red), and both source types need to
 * agree on what counts as "marked".
 *
 * Since extraction happens per text-run with no visibility into sibling
 * options' colors, this can't literally compare "different from the other
 * 3" - instead it treats any non-default (non-black/gray, non-empty) color
 * as a mark, which is what "colored differently to highlight the answer"
 * looks like in practice: everything else stays default black/gray text.
 */
class QuizAnswerMarkColor
{
  private const DEFAULT_KEYWORDS = ['black', 'inherit', 'initial', 'unset', 'currentcolor', 'transparent', 'none', 'windowtext'];

  public static function isMarked(?string $color): bool
  {
    if (!$color) {
      return false;
    }
    $color = trim(strtolower($color));
    if ($color === '' || in_array($color, self::DEFAULT_KEYWORDS, true)) {
      return false;
    }

    if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $color, $m)) {
      return self::rgbLooksColored((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    $hex = ltrim($color, '#');
    if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (preg_match('/^[0-9a-f]{6}$/', $hex)) {
      return self::rgbLooksColored(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    // Any other named CSS color (e.g. "blue", "green", "orange", "crimson")
    // that isn't in the default-keyword list above counts as marked.
    return true;
  }

  // "Default-looking" text is black or a plain gray (R≈G≈B) that's dark
  // enough to be body-text gray, not a deliberately chosen light/pastel
  // gray. Anything else - any real hue, or a conspicuously light/white
  // shade - counts as an intentional highlight.
  private static function rgbLooksColored(int $r, int $g, int $b): bool
  {
    $isGrayscale = abs($r - $g) < 15 && abs($g - $b) < 15 && abs($r - $b) < 15;
    $isDarkOrBlack = max($r, $g, $b) < 90;
    return !($isGrayscale && $isDarkOrBlack);
  }
}
