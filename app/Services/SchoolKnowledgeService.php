<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\Topic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * What the school's youth union account (config forum.school_account) has
 * posted, for Yoyo AI: when a question is about the school, the assistant
 * names a few search words and gets back the handful of that account's
 * posts that match, each cut down to the part around the match.
 *
 * Read only, and narrow on purpose: the assistant never writes a query -
 * it only supplies words, and this class runs the same SELECT on public,
 * approved posts of that one account every time. It never sends the whole
 * archive either: MAX_POSTS excerpts of EXCERPT_LENGTH characters at most.
 */
class SchoolKnowledgeService
{
  /** Used when the config has no value (a config cache from before the key existed). */
  private const DEFAULT_ACCOUNT = 'DoanTruongCBH';

  private const MAX_TERMS = 6;

  /** One subject is often spread over several posts (announcement, recap, results). */
  private const MAX_POSTS = 6;
  private const EXCERPT_LENGTH = 500;

  /** Newest matching posts that are scored; older matches are not looked at. */
  private const MAX_CANDIDATES = 300;

  /** A post is kept when it scores at least this part of the best post's score. */
  private const MIN_RELATIVE_SCORE = 0.3;

  // Words that say nothing about the subject (folded to plain ASCII).
  private const STOP_WORDS = [
    'la', 'va', 'cua', 'co', 'cho', 'cac', 'nhung', 'mot', 'nay', 'nao', 'gi', 'khi', 'thi', 'duoc', 'khong',
    've', 'voi', 'trong', 'tai', 'tu', 'den', 'da', 'se', 'dang', 'nhu', 'the', 'ra', 'vao', 'hay', 'hoac',
    'ai', 'bao', 'nhieu', 'o', 'dau', 'sao', 'vay', 'a', 'nhe', 'minh', 'ban', 'toi', 'em', 'anh',
    'truong', 'chuyen', 'bien', 'hoa', 'cbh', 'thpt', 'doan', 'ngay', 'thang',
  ];

  /**
   * The account's posts that match the search words, best match first.
   * `ok` is false when the lookup itself failed (so the caller can say
   * "couldn't check" instead of "nothing was posted").
   *
   * @return array{ok: bool, posts: array<int, array{title: string, date: string, url: string, excerpt: string}>}
   */
  public function search(string $query): array
  {
    try {
      $username = config('forum.school_account') ?: self::DEFAULT_ACCOUNT;
      $account = AuthAccount::where('username', $username)->first(['id', 'username']);
      if (!$account) {
        Log::warning('School post lookup: the school account does not exist', ['username' => $username]);

        return ['ok' => false, 'posts' => []];
      }

      $terms = self::terms($query);
      if (!$terms) {
        return ['ok' => true, 'posts' => []];
      }

      $candidates = Topic::query()
        ->where('user_id', $account->id)
        ->where('hidden', 0)
        ->where('privacy', 'public')
        ->where('moderation_status', 'approved')
        ->where('anonymous', false)
        ->where(function ($q) use ($terms) {
          foreach ($terms as $term) {
            foreach (self::likePatterns($term) as $like) {
              $q->orWhere('title', 'LIKE', $like)
                ->orWhere('description', 'LIKE', $like)
                ->orWhere('content_html', 'LIKE', $like);
            }
          }
        })
        ->orderBy('created_at', 'desc')
        ->limit(self::MAX_CANDIDATES)
        ->get(['id', 'title', 'description', 'content_html', 'created_at']);

      // Which words each post really has (the database matches loosely:
      // parts of words, any accents).
      $rows = [];
      $found = array_fill_keys($terms, 0);
      foreach ($candidates as $topic) {
        $text = self::plainText((string) ($topic->description ?: $topic->content_html));
        $title = self::forms((string) $topic->title);
        $body = self::forms($text);

        $hits = [];
        foreach ($terms as $term) {
          $inTitle = self::has($term, $title);
          $inBody = self::has($term, $body);
          if ($inTitle || $inBody) {
            $hits[$term] = ($inTitle ? 3 : 0) + ($inBody ? 1 : 0);
            $found[$term]++;
          }
        }
        if ($hits) {
          $rows[] = ['topic' => $topic, 'text' => $text, 'hits' => $hits];
        }
      }

      // A word found in few posts says more about the subject than one found
      // in nearly all of them ("hoạt động"): it weighs more.
      $weights = [];
      foreach ($terms as $term) {
        $weights[$term] = $found[$term] > 0 ? log(1 + count($rows) / $found[$term]) : 0;
      }
      arsort($weights);
      $byWeight = array_keys($weights);

      foreach ($rows as &$row) {
        $row['score'] = 0;
        foreach ($row['hits'] as $term => $points) {
          $row['score'] += $points * $weights[$term];
        }
      }
      unset($row);

      $best = $rows ? max(array_column($rows, 'score')) : 0;
      $baseUrl = rtrim(env('APP_UI_URL', 'https://chuyenbienhoa.com'), '/');

      $posts = collect($rows)
        ->filter(fn($row) => $row['score'] > 0 && $row['score'] >= $best * self::MIN_RELATIVE_SCORE)
        // Best match first; the candidates are newest first, and the sort is
        // stable, so among equal matches the newer post wins.
        ->sortByDesc('score')
        ->take(self::MAX_POSTS)
        ->map(fn($row) => [
          'title' => trim((string) $row['topic']->title),
          'date' => $row['topic']->created_at ? $row['topic']->created_at->format('d/m/Y') : '',
          'url' => $baseUrl . '/' . $account->username . '/posts/' . $row['topic']->id . '-' . $row['topic']->getSlug(),
          'excerpt' => self::excerpt($row['text'], $byWeight),
        ])
        ->values()
        ->all();

      return ['ok' => true, 'posts' => $posts];
    } catch (\Throwable $e) {
      Log::warning('School post lookup failed', [
        'query' => $query,
        'error' => $e->getMessage(),
        'at' => basename($e->getFile()) . ':' . $e->getLine(),
      ]);

      return ['ok' => false, 'posts' => []];
    }
  }

  /**
   * The words of a query worth searching for: lower case, without filler
   * words, each once, MAX_TERMS at most. A date is one term, written
   * day/month ("26/3" - also for "26/03", "26-3", "26.03.2026"); the other
   * words are kept as typed (with their accents).
   *
   * @return string[]
   */
  public static function terms(string $query): array
  {
    $query = mb_strtolower($query);
    $terms = [];

    $query = preg_replace_callback(
      '/(?<!\d)(\d{1,2})\s*[\/\-.]\s*(\d{1,2})(?:\s*[\/\-.]\s*\d{2,4})?(?!\d)/u',
      function ($date) use (&$terms) {
        $day = (int) $date[1];
        $month = (int) $date[2];
        if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12) {
          $terms["{$day}/{$month}"] = "{$day}/{$month}";

          return ' ';
        }

        return $date[0];
      },
      $query
    ) ?? $query;

    foreach (preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
      $folded = self::fold($word);
      // A single letter matches almost every post; numbers (a year, a class)
      // are kept whatever their length.
      if ((mb_strlen($word) < 2 && !ctype_digit($word)) || in_array($folded, self::STOP_WORDS, true)) {
        continue;
      }
      $terms['w:' . $folded] = $word;
    }

    return array_slice(array_values($terms), 0, self::MAX_TERMS);
  }

  /**
   * How well a post matches: each search word it has counts once however
   * often it appears, three times as much in the title as in the body, times
   * the word's weight ($weights: term => weight, 1 when not given).
   *
   * Whole words only ("hội" is not in "thời"). A word typed with its accents
   * has to match with them - "hội", "hỏi" and "hơi" are different words -
   * while one typed without matches any of them. A date matches however it
   * is written ("26/3", "26/03", "26-3", "26 tháng 3").
   */
  public static function score(array $terms, string $title, string $text, array $weights = []): float
  {
    $title = self::forms($title);
    $text = self::forms($text);
    $score = 0.0;

    foreach ($terms as $term) {
      $points = (self::has($term, $title) ? 3 : 0) + (self::has($term, $text) ? 1 : 0);
      $score += $points * ($weights[$term] ?? 1);
    }

    return $score;
  }

  /**
   * The part of a post around the first of $terms found in it - so pass the
   * most telling term first.
   */
  public static function excerpt(string $text, array $terms): string
  {
    if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
      return $text;
    }

    $position = 0;
    foreach ($terms as $term) {
      $found = self::position($text, (string) $term);
      if ($found !== null) {
        $position = $found;
        break;
      }
    }

    // Start a little before the match, so the sentence it is in is whole.
    $start = max(0, min($position - 120, mb_strlen($text) - self::EXCERPT_LENGTH));

    return ($start > 0 ? '…' : '')
      . trim(mb_substr($text, $start, self::EXCERPT_LENGTH))
      . ($start + self::EXCERPT_LENGTH < mb_strlen($text) ? '…' : '');
  }

  /**
   * A post's markdown (or HTML) as one line of plain text.
   */
  public static function plainText(string $markup): string
  {
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', ' ', $markup) ?? $markup;
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
    // A tag is a word boundary ("</p><p>").
    $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[*`>#~]+/u', '', $text) ?? $text;

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
  }

  /** "26/3" -> [26, 3]; null for a term that is not a date. */
  private static function date(string $term): ?array
  {
    return preg_match('/\A(\d{1,2})\/(\d{1,2})\z/', $term, $date) ? [(int) $date[1], (int) $date[2]] : null;
  }

  /** A date however it is written: 26/3, 26/03, 26-3, 26.03, 26 tháng 3. */
  private static function datePattern(array $date): string
  {
    return '/(?<![\d\/.\-])0?' . $date[0] . '(?:\s*[\/\-.]\s*|\s+tháng\s+)0?' . $date[1] . '(?!\d)/iu';
  }

  /**
   * LIKE patterns that find a term in the database (looser than has():
   * they only pick the candidates).
   *
   * @return string[]
   */
  private static function likePatterns(string $term): array
  {
    $date = self::date($term);
    if ($date === null) {
      return ['%' . addcslashes($term, '%_\\') . '%'];
    }

    [$day, $month] = $date;
    $months = $month < 10 ? [(string) $month, '0' . $month] : [(string) $month];
    $patterns = [];
    foreach ($months as $m) {
      foreach (['/', '-', '.', ' tháng '] as $separator) {
        $patterns[] = '%' . $day . $separator . $m . '%';
      }
    }

    return $patterns;
  }

  /**
   * A text in the three forms has() looks at: lower case as written, and
   * as space-separated words (with a space at both ends) with and without
   * accents.
   *
   * @return array{raw: string, exact: string, folded: string}
   */
  private static function forms(string $text): array
  {
    $raw = mb_strtolower($text);
    $exact = ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $raw) ?? $raw) . ' ';

    return ['raw' => $raw, 'exact' => $exact, 'folded' => self::fold($exact)];
  }

  private static function has(string $term, array $forms): bool
  {
    $date = self::date($term);
    if ($date !== null) {
      return (bool) preg_match(self::datePattern($date), $forms['raw']);
    }

    $term = mb_strtolower($term);
    $form = $term === self::fold($term) ? 'folded' : 'exact';

    return str_contains($forms[$form], " {$term} ");
  }

  /** Where a term first appears in a text, in characters; null when it doesn't. */
  private static function position(string $text, string $term): ?int
  {
    $date = self::date($term);
    if ($date !== null) {
      return preg_match(self::datePattern($date), $text, $match, PREG_OFFSET_CAPTURE)
        ? mb_strlen(substr($text, 0, $match[0][1]))
        : null;
    }

    $found = mb_stripos($text, $term);

    return $found === false ? null : $found;
  }

  /** Lower case without accents, so "hoi trai" finds "Hội trại". */
  private static function fold(string $text): string
  {
    return mb_strtolower(Str::ascii($text));
  }
}
