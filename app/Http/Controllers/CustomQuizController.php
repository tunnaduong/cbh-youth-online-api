<?php

namespace App\Http\Controllers;

use App\Models\QuizSet;
use App\Services\CustomQuizParsingService;
use App\Services\LocalQuizContentParser;
use App\Services\QuizAnswerMarkColor;
use App\Services\QuizDocumentExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a user build their own quiz set from typed rich text (bold/underline
 * marking the correct option) or an uploaded .docx/.txt/.pdf, instead of the
 * AI picking a topic. Never touches the AI-question bank (cyo_quiz_questions)
 * - a custom set only ever exists as its own QuizSet row, shared purely via
 * link (see QuizController::preview/join for how the resulting set is played).
 */
class CustomQuizController extends Controller
{
  private const MAX_QUESTIONS = 100;
  private const DIFFICULTIES = ['easy', 'medium', 'hard'];

  public function store(Request $request)
  {
    $request->validate([
      'title' => 'nullable|string|max:150',
      'difficulty' => 'nullable|string|in:' . implode(',', self::DIFFICULTIES),
      'content_html' => 'nullable|string|max:100000',
      'content_text' => 'nullable|string|max:100000',
      'file' => 'nullable|file|mimes:docx,txt,pdf|max:10240', // 10MB
    ]);

    if (!$request->filled('content_html') && !$request->filled('content_text') && !$request->hasFile('file')) {
      return response()->json([
        'message' => 'Cần nhập nội dung hoặc tải lên file (.docx, .txt, .pdf).',
      ], 422);
    }

    $user = Auth::user();
    $difficulty = $request->input('difficulty', 'medium');

    try {
      if ($request->hasFile('file')) {
        $markdown = app(QuizDocumentExtractionService::class)->extract($request->file('file'));
      } elseif ($request->filled('content_text')) {
        // Already in the "**bold**"/<u>/<mark> marker convention -
        // sent directly by clients (e.g. the mobile app's live-markdown
        // editor) that compose in this markup natively instead of HTML.
        $markdown = $request->input('content_text');
      } else {
        $markdown = $this->htmlToMarkup($request->input('content_html'));
      }

      if (trim($markdown) === '') {
        return response()->json(['message' => 'Không tìm thấy nội dung để phân tích.'], 422);
      }

      // Fast, free, deterministic pass first - only falls through to the AI
      // call when the content isn't consistently formatted enough to trust
      // (see LocalQuizContentParser's own doc comment for the "all or
      // nothing" reasoning).
      $localResult = app(LocalQuizContentParser::class)->parse($markdown);
      $parsed = !empty($localResult['questions'])
        ? $localResult
        : app(CustomQuizParsingService::class)->parse($markdown);
    } catch (\Throwable $e) {
      return response()->json([
        'message' => $e->getMessage() ?: 'Không thể xử lý nội dung, vui lòng thử lại.',
      ], 422);
    }

    $questions = array_slice($parsed['questions'], 0, self::MAX_QUESTIONS);
    // Slicing can leave ids non-contiguous if MAX_QUESTIONS was hit - keep
    // them 1..n like every other quiz flow expects.
    foreach ($questions as $i => &$q) {
      $q['id'] = $i + 1;
    }
    unset($q);

    $title = trim((string) $request->input('title')) ?: 'Bộ đề tùy chỉnh';

    $quizSet = QuizSet::create([
      'creator_id' => $user->id,
      'is_custom' => true,
      'topic' => $title,
      'grade' => null,
      'difficulty' => $difficulty,
      'question_count' => count($questions),
      'questions' => $questions,
      'served_count' => 0,
    ]);

    return response()->json([
      'quiz_set_id' => $quizSet->id,
      'topic' => $quizSet->topic,
      'difficulty' => $quizSet->difficulty,
      'question_count' => $quizSet->question_count,
    ]);
  }

  /**
   * Converts the rich-text editor's HTML into the same "bold text,
   * <u>underline</u>, <mark>distinctly-colored text</mark> marks the correct
   * option" convention used by QuizDocumentExtractionService/
   * LocalQuizContentParser/CustomQuizParsingService, so every source type
   * (typed, .docx, .txt, .pdf) funnels into identical downstream parsing.
   */
  private function htmlToMarkup(string $html): string
  {
    if (trim($html) === '') {
      return '';
    }

    $doc = new \DOMDocument();
    // Wrap in a UTF-8 meta + root so DOMDocument doesn't mangle non-ASCII
    // text or complain about a missing doctype.
    @$doc->loadHTML(
      '<?xml encoding="utf-8"?><div>' . $html . '</div>',
      LIBXML_NOERROR | LIBXML_NOWARNING
    );

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) {
      return trim(strip_tags($html));
    }

    return trim($this->nodeToMarkup($root));
  }

  private function nodeToMarkup(\DOMNode $node): string
  {
    if ($node->nodeType === XML_TEXT_NODE) {
      return $node->textContent;
    }

    if ($node->nodeType !== XML_ELEMENT_NODE) {
      return '';
    }

    $tag = strtolower($node->nodeName);
    $inner = '';
    foreach ($node->childNodes as $child) {
      $inner .= $this->nodeToMarkup($child);
    }

    // A distinct text color is a third way (alongside bold/underline) a
    // creator can mark the correct option - editors express it as either a
    // `color` attribute (<font color="...">) or an inline
    // `style="color: ..."`.
    if ($node instanceof \DOMElement && $this->hasMarkedColor($node)) {
      $inner = $this->wrapMarker($inner, '<mark>', '</mark>');
    }

    return match ($tag) {
      'b', 'strong' => $this->wrapMarker($inner, '**', '**'),
      'u' => $this->wrapMarker($inner, '<u>', '</u>'),
      'br' => "\n",
      'p', 'div', 'li' => $inner . "\n",
      default => $inner,
    };
  }

  /**
   * Wraps $inner in an open/close marker, but keeps any trailing newline(s)
   * outside the marker instead of inside it. Some rich-text editors nest a
   * block element (p/div/li) *inside* an inline bold/underline/color node
   * instead of the reverse - when that happens $inner already ends with the
   * "\n" the block tag appended, and wrapping it verbatim would produce
   * "**text\n**" (closing marker glued to the start of the next line)
   * instead of the intended "**text**\n".
   */
  private function wrapMarker(string $inner, string $open, string $close): string
  {
    if (preg_match('/^(.*?)(\n*)$/us', $inner, $m)) {
      return $open . $m[1] . $close . $m[2];
    }
    return $open . $inner . $close;
  }

  private function hasMarkedColor(\DOMElement $node): bool
  {
    if (QuizAnswerMarkColor::isMarked($node->getAttribute('color'))) {
      return true;
    }

    $style = $node->getAttribute('style');
    if ($style && preg_match('/color\s*:\s*([^;]+)/i', $style, $m)) {
      return QuizAnswerMarkColor::isMarked(trim($m[1]));
    }

    return false;
  }
}
