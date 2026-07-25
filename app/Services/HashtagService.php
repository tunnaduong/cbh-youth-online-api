<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Topic;
use DOMDocument;
use DOMText;

/**
 * Extracts hashtags from rendered post HTML, turns them into clickable
 * links, and keeps the cyo_hashtags / cyo_topic_hashtags tables in sync.
 */
class HashtagService
{
  /**
   * Matches a hashtag: "#" not preceded by a word char or "&" (so we don't
   * match inside HTML entities like &#039;), followed by one or more
   * unicode letters/numbers/underscores.
   */
  private const HASHTAG_PATTERN = '/(?<![\w&])#([\p{L}\p{N}_]+)/u';

  /**
   * Tag names for which we won't descend into child text nodes when
   * linkifying (already-linked or non-prose content).
   */
  private const SKIP_TAGS = ['a', 'code', 'pre', 'script', 'style'];

  /**
   * Find hashtags in HTML and wrap them in links, without touching
   * hashtags that already live inside an <a>, <code>, <pre>, etc.
   *
   * @param  string  $html
   * @return array{html: string, tags: string[]}
   */
  public static function linkify(string $html): array
  {
    if (trim($html) === '') {
      return ['html' => $html, 'tags' => []];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Wrap in a container + force UTF-8 so DOMDocument doesn't mangle accents.
    // LIBXML_HTML_NOIMPLIED keeps libxml from auto-inserting <html><body>,
    // so the wrapper div ends up as the document element itself.
    $dom->loadHTML(
      '<?xml encoding="utf-8"?><div>' . $html . '</div>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $dom->documentElement;
    if (!$root) {
      return ['html' => $html, 'tags' => []];
    }

    $tags = [];
    self::walkAndLinkify($dom, $root, $tags);

    $innerHtml = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
      $innerHtml .= $dom->saveHTML($child);
    }

    return [
      'html' => $innerHtml,
      'tags' => array_values(array_unique($tags)),
    ];
  }

  /**
   * Recursively walk DOM nodes, replacing hashtag matches in text nodes
   * with <a> elements, while skipping subtrees rooted at SKIP_TAGS.
   *
   * @param  \DOMDocument  $dom
   * @param  \DOMNode  $node
   * @param  array  $tags
   * @return void
   */
  private static function walkAndLinkify(DOMDocument $dom, $node, array &$tags): void
  {
    foreach (iterator_to_array($node->childNodes) as $child) {
      if ($child instanceof DOMText) {
        self::replaceTextNode($dom, $child, $tags);
        continue;
      }

      if ($child->nodeType === XML_ELEMENT_NODE && in_array(strtolower($child->nodeName), self::SKIP_TAGS, true)) {
        continue;
      }

      if ($child->hasChildNodes()) {
        self::walkAndLinkify($dom, $child, $tags);
      }
    }
  }

  /**
   * Replace a single text node with a mix of text + <a> nodes if it
   * contains any hashtags, collecting the matched tags along the way.
   *
   * @param  \DOMDocument  $dom
   * @param  \DOMText  $textNode
   * @param  array  $tags
   * @return void
   */
  private static function replaceTextNode(DOMDocument $dom, DOMText $textNode, array &$tags): void
  {
    $text = $textNode->wholeText;

    if (!preg_match(self::HASHTAG_PATTERN, $text)) {
      return;
    }

    $fragment = $dom->createDocumentFragment();
    $lastOffset = 0;

    preg_match_all(self::HASHTAG_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $index => $match) {
      [$fullMatch, $offset] = $match;
      $tag = $matches[1][$index][0];
      $normalizedTag = mb_strtolower($tag);
      $tags[] = $normalizedTag;

      if ($offset > $lastOffset) {
        $fragment->appendChild($dom->createTextNode(substr($text, $lastOffset, $offset - $lastOffset)));
      }

      $link = $dom->createElement('a', '#' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'));
      $link->setAttribute('href', '/search?type=hashtag&q=' . rawurlencode($normalizedTag));
      $link->setAttribute('class', 'hashtag-link');
      $link->setAttribute('data-hashtag', $normalizedTag);
      $fragment->appendChild($link);

      $lastOffset = $offset + strlen($fullMatch);
    }

    if ($lastOffset < strlen($text)) {
      $fragment->appendChild($dom->createTextNode(substr($text, $lastOffset)));
    }

    $textNode->parentNode->replaceChild($fragment, $textNode);
  }

  /**
   * Upsert the given tags and sync them onto the topic's hashtags relation.
   *
   * @param  \App\Models\Topic  $topic
   * @param  string[]  $tags
   * @return void
   */
  public static function syncTopicHashtags(Topic $topic, array $tags): void
  {
    $hashtagIds = [];

    foreach (array_unique($tags) as $tag) {
      $hashtagIds[] = Hashtag::firstOrCreate(['tag' => $tag])->id;
    }

    $topic->hashtags()->sync($hashtagIds);
  }
}
