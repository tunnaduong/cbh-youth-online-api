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
  public static function make(int $userId, string $slug, string $upstreamUrl, ?int $ttl = null): string
  {
    $payload = json_encode([
      'u' => $userId,
      'g' => $slug,
      'o' => $upstreamUrl,
      'e' => time() + ($ttl ?? (int) config('gameproxy.link_ttl')),
    ], JSON_UNESCAPED_SLASHES);

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
