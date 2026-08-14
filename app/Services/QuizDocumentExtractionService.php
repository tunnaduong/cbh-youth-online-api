<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts text content from a creator-uploaded .docx/.txt/.pdf into the
 * same "**bold**/<u>underline</u>/<mark>distinctly-colored text</mark> marks
 * the correct option" markdown convention used everywhere else in the
 * custom-quiz flow (see LocalQuizContentParser and CustomQuizParsingService).
 *
 * .txt content is expected to already use that convention (the frontend's
 * rich-text editor is responsible for it when the creator types directly).
 * .docx runs get their real bold/underline formatting converted to it.
 * .pdf has no reliable per-run formatting via this library, so it comes
 * back as plain text - a PDF-sourced quiz generally needs textual answer
 * cues (e.g. "Đáp án: B") for the AI fallback pass to find the answer.
 */
class QuizDocumentExtractionService
{
  private const SUPPORTED_EXTENSIONS = ['txt', 'docx', 'pdf'];

  public function isSupported(UploadedFile $file): bool
  {
    return in_array(strtolower($file->getClientOriginalExtension()), self::SUPPORTED_EXTENSIONS, true);
  }

  /**
   * @throws \RuntimeException  On an unsupported extension or extraction failure.
   */
  public function extract(UploadedFile $file): string
  {
    $extension = strtolower($file->getClientOriginalExtension());

    try {
      return match ($extension) {
        'txt' => $this->extractTxt($file),
        'docx' => $this->extractDocx($file),
        'pdf' => $this->extractPdf($file),
        default => throw new \RuntimeException('Định dạng file không được hỗ trợ (chỉ hỗ trợ .docx, .txt, .pdf).'),
      };
    } catch (\RuntimeException $e) {
      throw $e;
    } catch (\Throwable $e) {
      throw new \RuntimeException('Không thể đọc nội dung file: ' . $e->getMessage());
    }
  }

  private function extractTxt(UploadedFile $file): string
  {
    $content = file_get_contents($file->getRealPath());
    return $content === false ? '' : $content;
  }

  private function extractDocx(UploadedFile $file): string
  {
    $phpWord = IOFactory::createReader('Word2007')->load($file->getRealPath());

    $lines = [];
    foreach ($phpWord->getSections() as $section) {
      foreach ($section->getElements() as $element) {
        $line = $this->elementToMarkdown($element);
        if ($line !== null && trim($line) !== '') {
          $lines[] = $line;
        }
      }
    }

    return implode("\n", $lines);
  }

  private function elementToMarkdown($element): ?string
  {
    if ($element instanceof TextRun) {
      $parts = [];
      foreach ($element->getElements() as $child) {
        $parts[] = $this->elementToMarkdown($child) ?? '';
      }
      return implode('', $parts);
    }

    if ($element instanceof Text) {
      $text = $element->getText();
      if (!is_string($text) || $text === '') {
        return null;
      }

      $fontStyle = $element->getFontStyle();
      $isBold = false;
      $isUnderline = false;
      $isMarkedColor = false;
      if (is_object($fontStyle)) {
        $isBold = method_exists($fontStyle, 'isBold') && $fontStyle->isBold();
        $underline = method_exists($fontStyle, 'getUnderline') ? $fontStyle->getUnderline() : null;
        $isUnderline = $underline && $underline !== 'none';
        $color = method_exists($fontStyle, 'getColor') ? $fontStyle->getColor() : null;
        $isMarkedColor = QuizAnswerMarkColor::isMarked($color);
      }

      if ($isBold) {
        $text = "**{$text}**";
      }
      if ($isUnderline) {
        $text = "<u>{$text}</u>";
      }
      if ($isMarkedColor) {
        $text = "<mark>{$text}</mark>";
      }
      return $text;
    }

    // Other element types (images, tables, etc.) aren't meaningful for a
    // text-based quiz source - skip silently rather than fail the whole
    // extraction over unsupported content.
    return null;
  }

  private function extractPdf(UploadedFile $file): string
  {
    $parser = new PdfParser();
    $pdf = $parser->parseFile($file->getRealPath());
    return $pdf->getText();
  }
}
