<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a "Sign in with Apple" identity token the way Apple's own docs
 * require: check the RS256 signature against Apple's published public keys,
 * then the standard JWT claims (issuer, expiry, and - when APPLE_CLIENT_ID is
 * configured - audience).
 *
 * Before this existed, AuthController::loginWithProvider() just base64-decoded
 * the JWT payload and trusted whatever "sub"/"email" it contained, with no
 * signature check at all - anyone could send a self-forged token claiming to
 * be any Apple user (or any email address) and get logged in or a new account
 * created under that identity. This class is what actually stops that.
 */
class AppleIdTokenVerifier
{
  private const KEYS_URL = 'https://appleid.apple.com/auth/keys';
  private const ISSUER = 'https://appleid.apple.com';
  private const CACHE_KEY = 'apple_id_token_verifier.jwks';

  /**
   * @return array<string, mixed>|null  The verified payload (sub, email, ...), or null if the token is invalid/unverifiable.
   */
  public static function verify(string $idToken): ?array
  {
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
      return null;
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $header = json_decode(self::base64UrlDecode($headerB64), true);
    $payload = json_decode(self::base64UrlDecode($payloadB64), true);
    $signature = self::base64UrlDecode($signatureB64);

    if (!is_array($header) || !is_array($payload) || $signature === '') {
      return null;
    }

    if (($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
      Log::warning('AppleIdTokenVerifier: unexpected JWT header', ['header' => $header]);
      return null;
    }

    $publicKey = self::publicKeyForKid($header['kid']);
    if (!$publicKey) {
      Log::warning('AppleIdTokenVerifier: no matching Apple public key for kid', ['kid' => $header['kid']]);
      return null;
    }

    $signedInput = $headerB64 . '.' . $payloadB64;
    $verified = openssl_verify($signedInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) {
      Log::warning('AppleIdTokenVerifier: signature verification failed');
      return null;
    }

    if (($payload['iss'] ?? null) !== self::ISSUER) {
      Log::warning('AppleIdTokenVerifier: unexpected issuer', ['iss' => $payload['iss'] ?? null]);
      return null;
    }

    if (!isset($payload['exp']) || time() >= (int) $payload['exp']) {
      Log::warning('AppleIdTokenVerifier: token expired');
      return null;
    }

    // Only enforced when configured - the app may accept the token from more
    // than one Apple audience (native app bundle id vs a web Service ID), and
    // guessing the wrong value here would be worse (break every legitimate
    // Apple sign-in) than skipping this one check when it isn't set.
    $allowedAudiences = array_filter(array_map('trim', explode(',', (string) env('APPLE_CLIENT_ID', ''))));
    if (!empty($allowedAudiences) && !in_array((string) ($payload['aud'] ?? ''), $allowedAudiences, true)) {
      Log::warning('AppleIdTokenVerifier: unexpected audience', ['aud' => $payload['aud'] ?? null]);
      return null;
    }

    return $payload;
  }

  private static function publicKeyForKid(string $kid): ?string
  {
    $keys = Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
      $response = Http::timeout(10)->get(self::KEYS_URL);
      return $response->successful() ? ($response->json('keys') ?? []) : [];
    });

    $jwk = collect($keys)->firstWhere('kid', $kid);
    if (!$jwk || ($jwk['kty'] ?? null) !== 'RSA') {
      return null;
    }

    return self::rsaPublicKeyFromJwk($jwk['n'], $jwk['e']);
  }

  /**
   * Builds a PEM-encoded RSA public key from a JWK's base64url-encoded
   * modulus (n) and exponent (e), the two components Apple's JWKS exposes.
   * PHP's openssl_* functions don't accept raw JWK components directly, only
   * a PEM/DER key, so this manually builds the minimal ASN.1 DER structure
   * for an RSA public key and wraps it as PEM.
   */
  private static function rsaPublicKeyFromJwk(string $n, string $e): string
  {
    $modulus = self::base64UrlDecode($n);
    $exponent = self::base64UrlDecode($e);

    $modulusEncoded = self::derEncodeUnsignedInteger($modulus);
    $exponentEncoded = self::derEncodeUnsignedInteger($exponent);

    $sequence = self::derEncode(0x30, $modulusEncoded . $exponentEncoded);
    $bitString = self::derEncode(0x03, "\x00" . $sequence);

    // RSA public key OID (1.2.840.113549.1.1.1) + NULL params, standard
    // AlgorithmIdentifier prefix for a SubjectPublicKeyInfo structure.
    $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
    $spki = self::derEncode(0x30, $algorithmIdentifier . $bitString);

    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";

    return $pem;
  }

  private static function derEncodeUnsignedInteger(string $bytes): string
  {
    // A DER INTEGER is signed - if the high bit of the first byte is set,
    // it would be read as negative, so an unsigned value needs a leading
    // 0x00 byte in that case.
    if (ord($bytes[0]) > 0x7f) {
      $bytes = "\x00" . $bytes;
    }
    return self::derEncode(0x02, $bytes);
  }

  private static function derEncode(int $tag, string $contents): string
  {
    $length = strlen($contents);
    if ($length < 128) {
      $lengthBytes = chr($length);
    } else {
      $lengthBytes = ltrim(pack('N', $length), "\x00");
      $lengthBytes = chr(0x80 | strlen($lengthBytes)) . $lengthBytes;
    }
    return chr($tag) . $lengthBytes . $contents;
  }

  private static function base64UrlDecode(string $data): string
  {
    $padded = strtr($data, '-_', '+/');
    $padLength = 4 - (strlen($padded) % 4);
    if ($padLength < 4) {
      $padded .= str_repeat('=', $padLength);
    }
    return (string) base64_decode($padded);
  }
}
