<?php

namespace App\Services;

/**
 * Strips the "**bold**"/<u>underline</u>/<mark>color</mark> answer-marking
 * convention (see QuizDocumentExtractionService's doc comment) down to
 * plain text. Shared by both LocalQuizContentParser (deterministic pass)
 * and CustomQuizParsingService (AI pass) so a stray/unmatched marker - e.g.
 * one half of an underline that got split across two options by an
 * upstream extraction quirk - is scrubbed the same way regardless of which
 * parser ends up handling the document, instead of only being caught by
 * whichever one happens to run.
 */
class QuizMarkupStripper
{
  public static function strip(string $text): string
  {
    $text = preg_replace('/\*\*(.+?)\*\*/us', '$1', $text);
    $text = preg_replace('/<u>(.+?)<\/u>/us', '$1', $text);
    $text = preg_replace('/<mark>(.+?)<\/mark>/us', '$1', $text);
    $text = preg_replace('/__(.+?)__/us', '$1', $text);
    // Safety net: a marker pair split across two options by an upstream
    // extraction quirk (e.g. an underline spanning a paragraph/line break)
    // leaves one half unmatched here - strip any leftover lone open/close
    // tag rather than showing it as literal text to quiz-takers.
    $text = preg_replace('/<\/?u>|<\/?mark>/u', '', $text);
    $text = preg_replace('/\*\*|__/u', '', $text);
    return $text;
  }
}
