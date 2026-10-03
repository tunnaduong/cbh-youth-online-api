<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238: HMAC-SHA1, 6 digits, 30 second
 * step) - the variant every authenticator app (Google Authenticator,
 * Microsoft Authenticator, Authy...) supports out of the box.
 */
class TotpService
{
  public const PERIOD = 30;
  public const DIGITS = 6;

  private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

  /**
   * Generate a new random secret, base32 encoded (160 bits by default, the
   * size RFC 4226 recommends).
   */
  public static function generateSecret(int $bytes = 20): string
  {
    return self::base32Encode(random_bytes($bytes));
  }

  /**
   * The code for a given time step (unix time / PERIOD).
   */
  public static function codeAt(string $secret, int $step): string
  {
    $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);

    // Dynamic truncation (RFC 4226 section 5.3).
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
      | (ord($hash[$offset + 1]) << 16)
      | (ord($hash[$offset + 2]) << 8)
      | ord($hash[$offset + 3]);

    return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
  }

  /**
   * Check a code against the current time step, allowing $window steps of
   * clock drift either way. Returns the matched step (store it and pass it
   * back as $lastUsedStep so the same code can't be replayed) or null.
   */
  public static function verify(string $secret, string $code, ?int $lastUsedStep = null, int $window = 1, ?int $timestamp = null): ?int
  {
    $code = preg_replace('/\s+/', '', $code);
    if ($secret === '' || !preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
      return null;
    }

    $current = intdiv($timestamp ?? time(), self::PERIOD);

    for ($step = $current - $window; $step <= $current + $window; $step++) {
      if ($lastUsedStep !== null && $step <= $lastUsedStep) {
        continue;
      }
      if (hash_equals(self::codeAt($secret, $step), $code)) {
        return $step;
      }
    }

    return null;
  }

  /**
   * The otpauth:// URI authenticator apps read from a QR code (or open
   * directly when tapped on the phone that has the app).
   */
  public static function provisioningUri(string $secret, string $account, string $issuer): string
  {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
      . '?secret=' . $secret
      . '&issuer=' . rawurlencode($issuer)
      . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
  }

  public static function base32Encode(string $data): string
  {
    if ($data === '') {
      return '';
    }

    $bits = '';
    foreach (str_split($data) as $char) {
      $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
      $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
    }

    return $encoded;
  }

  public static function base32Decode(string $encoded): string
  {
    $encoded = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $encoded));
    if ($encoded === '') {
      return '';
    }

    $bits = '';
    foreach (str_split($encoded) as $char) {
      $bits .= str_pad(decbin(strpos(self::BASE32_ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
    }

    $decoded = '';
    foreach (str_split($bits, 8) as $byte) {
      if (strlen($byte) === 8) {
        $decoded .= chr(bindec($byte));
      }
    }

    return $decoded;
  }
}
