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
  private const MAX_TERMS = 6;
  private const MAX_POSTS = 4;
  private const EXCERPT_LENGTH = 600;

  /** Newest matching posts that are scored; older matches are not looked at. */
  private const MAX_CANDIDATES = 300;

  // Words that say nothing about the subject (folded to plain ASCII).
  private const STOP_WORDS = [
    'la', 'va', 'cua', 'co', 'cho', 'cac', 'nhung', 'mot', 'nay', 'nao', 'gi', 'khi', 'thi', 'duoc', 'khong',
    've', 'voi', 'trong', 'tai', 'tu', 'den', 'da', 'se', 'dang', 'nhu', 'the', 'ra', 'vao', 'hay', 'hoac',
    'ai', 'bao', 'nhieu', 'o', 'dau', 'sao', 'vay', 'a', 'nhe', 'minh', 'ban', 'toi', 'em', 'anh',
    'truong', 'chuyen', 'bien', 'hoa', 'cbh', 'thpt', 'doan',
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
      $terms = self::terms($query);
      $account = AuthAccount::where('username', config('forum.school_account'))->first(['id', 'username']);
      if (!$terms || !$account) {
        return ['ok' => (bool) $account, 'posts' => []];
      }

      $candidates = Topic::query()
        ->where('user_id', $account->id)
        ->where('hidden', 0)
        ->where('privacy', 'public')
        ->where('moderation_status', 'approved')
        ->where('anonymous', false)
        ->where(function ($q) use ($terms) {
          foreach ($terms as $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->orWhere('title', 'LIKE', $like)->orWhere('description', 'LIKE', $like);
          }
        })
        ->orderBy('created_at', 'desc')
        ->limit(self::MAX_CANDIDATES)
        ->get(['id', 'title', 'description', 'created_at']);

      $baseUrl = rtrim(env('APP_UI_URL', 'https://chuyenbienhoa.com'), '/');

      $posts = $candidates
        ->map(function (Topic $topic) use ($terms) {
          $text = self::plainText((string) $topic->description);

          return ['topic' => $topic, 'text' => $text, 'score' => self::score($terms, (string) $topic->title, $text)];
        })
        ->filter(fn($row) => $row['score'] > 0)
        // Best match first; the candidates are newest first, and the sort is
        // stable, so among equal matches the newer post wins.
        ->sortByDesc('score')
        ->take(self::MAX_POSTS)
        ->map(fn($row) => [
          'title' => trim((string) $row['topic']->title),
          'date' => $row['topic']->created_at?->format('d/m/Y') ?? '',
          'url' => $baseUrl . "/{$account->username}/posts/{$row['topic']->id}-" . $row['topic']->getSlug(),
          'excerpt' => self::excerpt($row['text'], $terms),
        ])
        ->values()
        ->all();

      return ['ok' => true, 'posts' => $posts];
    } catch (\Throwable $e) {
      Log::warning('School post lookup failed', ['query' => $query, 'error' => $e->getMessage()]);

      return ['ok' => false, 'posts' => []];
    }
  }

  /**
   * The words of a query worth searching for: lower case, without filler
   * words, each once, MAX_TERMS at most. Kept as typed (with their accents)
   * for the database, whose collation matches with or without them.
   *
   * @return string[]
   */
  public static function terms(string $query): array
  {
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $terms = [];

    foreach ($words as $word) {
      $folded = self::fold($word);
      // A single letter matches almost every post; numbers (a year, "20/11")
      // are kept whatever their length.
      if ((mb_strlen($word) < 2 && !ctype_digit($word)) || in_array($folded, self::STOP_WORDS, true)) {
        continue;
      }
      $terms[$folded] = $word;
    }

    return array_slice(array_values($terms), 0, self::MAX_TERMS);
  }

  /**
   * How well a post matches: a word in the title counts three times a word
   * in the body, and each word counts once however often it appears - so a
   * post about the subject beats one that merely repeats one common word.
   * A query of three or more words needs two of them to match.
   *
   * Whole words only ("hội" is not in "thời"). A word typed with its accents
   * has to match with them - "hội", "hỏi" and "hơi" are different words -
   * while one typed without matches any of them.
   */
  public static function score(array $terms, string $title, string $text): int
  {
    $title = self::words($title);
    $text = self::words($text);
    $score = 0;
    $matched = 0;

    foreach ($terms as $term) {
      $term = mb_strtolower((string) $term);
      $form = $term === self::fold($term) ? 'folded' : 'exact';
      $inTitle = str_contains($title[$form], " {$term} ");
      $inText = str_contains($text[$form], " {$term} ");
      if ($inTitle || $inText) {
        $matched++;
        $score += ($inTitle ? 3 : 0) + ($inText ? 1 : 0);
      }
    }

    return $matched >= min(2, count($terms)) ? $score : 0;
  }

  /**
   * A text as space-separated lower-case words with a space at both ends,
   * as written and without accents.
   *
   * @return array{exact: string, folded: string}
   */
  private static function words(string $text): array
  {
    $exact = ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text))) . ' ';

    return ['exact' => $exact, 'folded' => self::fold($exact)];
  }

  /**
   * The part of a post around the first search word found in it.
   */
  public static function excerpt(string $text, array $terms): string
  {
    if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
      return $text;
    }

    $position = null;
    foreach ($terms as $term) {
      $found = mb_stripos($text, $term);
      if ($found !== false && ($position === null || $found < $position)) {
        $position = $found;
      }
    }

    // Start a little before the match, so the sentence it is in is whole.
    $start = max(0, min(($position ?? 0) - 150, mb_strlen($text) - self::EXCERPT_LENGTH));

    return ($start > 0 ? '…' : '')
      . trim(mb_substr($text, $start, self::EXCERPT_LENGTH))
      . ($start + self::EXCERPT_LENGTH < mb_strlen($text) ? '…' : '');
  }

  /**
   * A post's markdown as one line of plain text.
   */
  public static function plainText(string $markdown): string
  {
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', ' ', $markdown);
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
    $text = strip_tags($text);
    $text = preg_replace('/[*`>#~]+/u', '', $text);

    return trim(preg_replace('/\s+/u', ' ', $text));
  }

  /** Lower case without accents, so "hoi trai" finds "Hội trại". */
  private static function fold(string $text): string
  {
    return mb_strtolower(Str::ascii($text));
  }
}
