<?php

namespace App\Services;

/**
 * Deterministic, non-AI first pass at parsing a creator's quiz content
 * (markdown with bold text, <u>underline</u>, <mark>distinctly-colored
 * text</mark> marking the correct option) into structured questions -
 * covers the common case of a cleanly-formatted document (numbered
 * questions, A/B/C/D-labeled options) without an AI round-trip.
 * CustomQuizController falls back to CustomQuizParsingService (AI) only
 * when this returns nothing usable, e.g. free-form or inconsistently
 * formatted content.
 *
 * Only multiple-choice questions are supported. A question block must have
 * between 2 and 4 answer options with exactly one marked as correct -
 * anything outside that (0 or 1 options, more than 4, or an ambiguous
 * marking) is left for the AI pass rather than guessed at here. A question
 * with only 2 or 3 real options still gets stored as a 4-option question,
 * with the missing option(s) left as empty strings, since every other part
 * of this app's quiz schema/UI assumes exactly 4 options (A-D).
 */
class LocalQuizContentParser
{
  // Matches a bolded, underlined, or distinctly-colored span and captures
  // its inner text - however the source marked the correct option.
  private const MARK_PATTERN = '/\*\*(.+?)\*\*|<u>(.+?)<\/u>|<mark>(.+?)<\/mark>|__(.+?)__/us';

  private const QUESTION_START_PATTERN = '/^\s*(?:câu|question|bài|q)\s*\.?\s*\d+\s*[\.:)]\s*/iu';
  // Any opening marker - markdown emphasis (**, __, *, _) or an HTML-style
  // <u>/<mark> tag - that can appear immediately before an option's label
  // when the whole option (including its label) was marked as correct.
  private const MARKER_PREFIX = '(?:[\*_]{0,2}|<u>|<mark>)';
  // Group 1 captures any opening marker (see MARKER_PREFIX) that opens
  // immediately before the label itself (e.g. "**C. text**" or "<u>C. text")
  // so it can be reattached to the option text rather than being silently
  // dropped - otherwise the marker pair becomes unbalanced and MARK_PATTERN
  // can no longer recognize/strip it.
  private const OPTION_LINE_PATTERN = '/^\s*(' . self::MARKER_PREFIX . ')[\(\[]?([A-Da-d1-4])[\.\):]\s*(.+)$/u';
  private const EXPLANATION_LABEL_PATTERN = '/^\s*(?:giải thích|explanation|lý do|reason)\s*[:.]?\s*/iu';

  /**
   * Returns parsed questions ONLY if every detected question block parsed
   * cleanly (exactly 4 options, exactly 1 marked) - i.e. the document is
   * consistently formatted enough to trust without an AI pass. If even one
   * block fails, the whole document is treated as "not confidently
   * parseable locally" and an empty array is returned, so the caller falls
   * back to AI for the full document rather than mixing local/AI results
   * and risking dropped or duplicated questions.
   *
   * @return array{questions: array}
   */
  public function parse(string $markdown): array
  {
    $blocks = $this->splitIntoBlocks($markdown);
    if (empty($blocks)) {
      return ['questions' => []];
    }

    $questions = [];
    foreach ($blocks as $block) {
      $question = $this->parseBlock($block);
      if ($question === null) {
        return ['questions' => []]; // one bad block - don't trust the rest
      }
      $question['id'] = count($questions) + 1;
      $questions[] = $question;
    }

    return ['questions' => $questions];
  }

  /**
   * Splits the source into per-question chunks. Prefers explicit "Câu N."/
   * "Question N." markers; if none are found anywhere in the text, falls
   * back to blank-line-separated paragraphs (still requires each chunk to
   * independently look like a real question in parseBlock()).
   */
  private function splitIntoBlocks(string $markdown): array
  {
    $lines = preg_split('/\r\n|\r|\n/', $markdown);

    $hasNumberedQuestions = false;
    foreach ($lines as $line) {
      if (preg_match(self::QUESTION_START_PATTERN, $line)) {
        $hasNumberedQuestions = true;
        break;
      }
    }

    $blocks = [];
    $current = [];

    if ($hasNumberedQuestions) {
      foreach ($lines as $line) {
        if (preg_match(self::QUESTION_START_PATTERN, $line)) {
          if (!empty($current)) {
            $blocks[] = implode("\n", $current);
          }
          $current = [preg_replace(self::QUESTION_START_PATTERN, '', $line)];
        } else {
          $current[] = $line;
        }
      }
      if (!empty($current)) {
        $blocks[] = implode("\n", $current);
      }
      return $blocks;
    }

    foreach ($lines as $line) {
      if (trim($line) === '') {
        if (!empty($current)) {
          $blocks[] = implode("\n", $current);
          $current = [];
        }
        continue;
      }
      $current[] = $line;
    }
    if (!empty($current)) {
      $blocks[] = implode("\n", $current);
    }
    return $blocks;
  }

  /**
   * @return array{question: string, options: string[], answer: string, explanation: string}|null
   */
  private function parseBlock(string $block): ?array
  {
    $lines = preg_split('/\r\n|\r|\n/', trim($block));
    $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    if (empty($lines)) {
      return null;
    }

    // A creator sometimes types all options on one physical line (e.g.
    // pasted text, or a rich-text editor that collapses <br>s), so split
    // any line containing 2+ "A./B./C./D." markers into one line per
    // marker before the per-line pass below.
    $expanded = [];
    foreach ($lines as $line) {
      foreach ($this->splitInlineOptions($line) as $part) {
        $expanded[] = $part;
      }
    }
    $lines = $expanded;

    $questionLines = [];
    $options = []; // ordered list of ['text' => ..., 'marked' => bool]
    $explanationLines = [];
    $mode = 'question';
    $sawLabeledOption = false;

    foreach ($lines as $line) {
      if (preg_match(self::EXPLANATION_LABEL_PATTERN, $line)) {
        $mode = 'explanation';
        $explanationLines[] = preg_replace(self::EXPLANATION_LABEL_PATTERN, '', $line);
        continue;
      }

      if ($mode === 'explanation') {
        $explanationLines[] = $line;
        continue;
      }

      if (preg_match(self::OPTION_LINE_PATTERN, $line, $m)) {
        $mode = 'options';
        $sawLabeledOption = true;
        $rawText = $m[1] . $m[3];
        $marked = (bool) preg_match(self::MARK_PATTERN, $rawText);
        $options[] = [
          'text' => trim($this->stripMarks($rawText)),
          'marked' => $marked,
        ];
        continue;
      }

      if ($mode === 'question') {
        $questionLines[] = $line;
      } else {
        // Stray line after options started but before an explanation label -
        // most likely a continuation of the last option (wrapped line).
        // But the line may also have a new option's label buried mid-line
        // (e.g. a previous option's trailing sentence got glued to the next
        // "C. ..." option on the same physical line) - split that off
        // rather than swallowing the new option into the previous one.
        $parts = $this->splitStrayLine($line);
        $continuation = array_shift($parts);
        if ($continuation !== null && $continuation !== '' && !empty($options)) {
          $last = array_pop($options);
          $marked = $last['marked'] || (bool) preg_match(self::MARK_PATTERN, $continuation);
          $options[] = [
            'text' => trim($last['text'] . ' ' . $this->stripMarks($continuation)),
            'marked' => $marked,
          ];
        }
        foreach ($parts as $part) {
          if (preg_match(self::OPTION_LINE_PATTERN, $part, $m)) {
            $sawLabeledOption = true;
            $rawText = $m[1] . $m[3];
            $marked = (bool) preg_match(self::MARK_PATTERN, $rawText);
            $options[] = [
              'text' => trim($this->stripMarks($rawText)),
              'marked' => $marked,
            ];
          }
        }
      }
    }

    // Nothing looked like an explicit "A./B./C./D." label anywhere in the
    // block - most often because a rich-text editor auto-converted the
    // typed letters into a real HTML list and the literal prefixes never
    // made it into the extracted text. Fall back to position: first line
    // is the question, every line after it (up to the explanation label)
    // is an option in the order it appears.
    if (!$sawLabeledOption && count($lines) >= 3) {
      $optionCandidates = array_slice($lines, 1, min(4, count($lines) - 1));
      // Re-derive using original line order rather than what fell through
      // the (unused-for-this-path) label loop above.
      $questionLines = [$lines[0]];
      $options = [];
      foreach ($optionCandidates as $line) {
        if (preg_match(self::EXPLANATION_LABEL_PATTERN, $line)) {
          break;
        }
        $marked = (bool) preg_match(self::MARK_PATTERN, $line);
        $options[] = [
          'text' => trim($this->stripMarks($line)),
          'marked' => $marked,
        ];
      }
    }

    if (count($options) < 2 || count($options) > 4) {
      return null;
    }

    $markedCount = count(array_filter($options, fn($o) => $o['marked']));
    if ($markedCount !== 1) {
      return null; // ambiguous or unmarked - let the AI pass handle it
    }

    $questionText = trim($this->stripMarks(implode(' ', $questionLines)));
    if ($questionText === '') {
      return null;
    }

    $answerIndex = null;
    foreach ($options as $i => $o) {
      if ($o['marked']) {
        $answerIndex = $i;
        break;
      }
    }

    $optionTexts = array_map(fn($o) => $o['text'], $options);
    // Pad a 2- or 3-option question up to 4 slots (empty strings) - the
    // marked answer's letter (A/B/C/D) is still based on its original
    // position, which padding at the end never shifts.
    while (count($optionTexts) < 4) {
      $optionTexts[] = '';
    }

    return [
      'question' => $questionText,
      'options' => $optionTexts,
      'answer' => ['A', 'B', 'C', 'D'][$answerIndex],
      'explanation' => trim($this->stripMarks(implode(' ', $explanationLines))),
    ];
  }

  private function stripMarks(string $text): string
  {
    return QuizMarkupStripper::strip($text);
  }

  /**
   * Splits a single physical line into multiple lines when it contains 2+
   * "A./B./C./D." markers run together (e.g. "A. foo B. bar C. baz D. qux"
   * typed or pasted as one line). Lines with 0 or 1 marker are returned
   * unchanged so normal single-option-per-line input isn't affected.
   *
   * @return string[]
   */
  private function splitInlineOptions(string $line): array
  {
    preg_match_all('/(?:^|\s)' . self::MARKER_PREFIX . '[\(\[]?[A-Da-d1-4][\.\):]\s/u', $line, $matches);
    if (count($matches[0]) < 2) {
      return [$line];
    }

    $parts = preg_split('/(?=(?:^|\s)' . self::MARKER_PREFIX . '[\(\[]?[A-Da-d1-4][\.\):]\s)/u', $line, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
  }

  /**
   * Splits a stray (unlabeled-at-start) line at any mid-line "A./B./C./D."
   * marker. The first returned part is the leading text (a continuation of
   * the previous option); any further parts each start with a label and are
   * new options. Called only once OPTION_LINE_PATTERN has already failed to
   * match the whole line, so the first part never itself starts with a
   * label.
   *
   * @return string[]
   */
  private function splitStrayLine(string $line): array
  {
    $parts = preg_split('/(?=(?:^|\s)' . self::MARKER_PREFIX . '[\(\[]?[A-Da-d1-4][\.\):]\s)/u', $line, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
  }
}
