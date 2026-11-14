<?php

namespace App\Services;

/**
 * The signed "play" link the game proxy accepts.
 *
 * Format: base64url(payload JSON) . "." . base64url(HMAC-SHA256(first part)).
 * The Go server (game-proxy/token.go) checks the same signature, so it never
 * needs the database: who is playing, which game, which address to stream from
 * and when the link expires are all inside the token.
 */
class GameProxyToken
{
  /**
   * @param array|null $options the game's proxy_options from the database
   *        (block_hosts / allow_hosts); sent along so the proxy needs no
   *        database of its own.
   */
  public static function make(int $userId, string $slug, string $upstreamUrl, ?int $ttl = null, ?array $options = null): string
  {
    $claims = [
      'u' => $userId,
      'g' => $slug,
      'o' => $upstreamUrl,
      'e' => time() + ($ttl ?? (int) config('gameproxy.link_ttl')),
    ];

    $x = self::cleanOptions($options);
    if ($x) {
      $claims['x'] = $x;
    }

    $payload = json_encode($claims, JSON_UNESCAPED_SLASHES);

    $body = self::b64($payload);

    return $body . '.' . self::b64(hash_hmac('sha256', $body, self::secret(), true));
  }

  /** Returns the payload array, or null when the token is malformed, forged or expired. */
  public static function verify(string $token): ?array
  {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
      return null;
    }

    [$body, $signature] = $parts;
    $expected = self::b64(hash_hmac('sha256', $body, self::secret(), true));
    if (!hash_equals($expected, $signature)) {
      return null;
    }

    $payload = json_decode(self::unb64($body), true);
    if (!is_array($payload) || ($payload['e'] ?? 0) < time()) {
      return null;
    }

    return $payload;
  }

  /** Only host lists, only strings, no duplicates; empty when there is nothing to send. */
  private static function cleanOptions(?array $options): array
  {
    $out = [];
    foreach (['block_hosts' => 'b', 'allow_hosts' => 'a'] as $key => $short) {
      $hosts = array_values(array_unique(array_filter(
        (array) ($options[$key] ?? []),
        fn($h) => is_string($h) && $h !== ''
      )));
      if ($hosts) {
        $out[$short] = $hosts;
      }
    }

    return $out;
  }

  private static function secret(): string
  {
    return (string) config('gameproxy.secret');
  }

  private static function b64(string $raw): string
  {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }

  private static function unb64(string $text): string
  {
    return (string) base64_decode(strtr($text, '-_', '+/'));
  }
}
