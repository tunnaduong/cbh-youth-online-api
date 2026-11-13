<?php

namespace App\Services;

use App\Models\VoipPushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends VoIP pushes (PushKit) straight to APNs, so an incoming call rings on
 * iOS through CallKit - the system's full-screen call UI, lock screen included,
 * even when the app is killed. Expo's push service cannot send this push type.
 *
 * Needs an APNs auth key (.p8) from the Apple Developer account
 * (Certificates, Identifiers & Profiles > Keys, "Apple Push Notifications
 * service" ticked): APNS_KEY_ID, APNS_TEAM_ID and the key itself in
 * APNS_PRIVATE_KEY_PATH (or its contents in APNS_PRIVATE_KEY). Without them
 * nothing is sent and calls fall back to the ordinary Expo notification.
 *
 * Talks HTTP/2 through curl (APNs refuses HTTP/1.1); PHP's curl must be built
 * with HTTP/2 support (it is on every current Linux distribution).
 */
class ApnsVoipService
{
  private const HOSTS = [
    'production' => 'https://api.push.apple.com',
    'sandbox' => 'https://api.sandbox.push.apple.com',
  ];

  public static function isConfigured(): bool
  {
    return self::privateKey() !== null
      && !empty(config('services.apns.key_id'))
      && !empty(config('services.apns.team_id'))
      && !empty(config('services.apns.bundle_id'));
  }

  /**
   * Ring a call on every active iOS device of the user.
   *
   * @return int Number of devices APNs accepted the push for.
   */
  public static function sendIncomingCall(int $userId, array $payload): int
  {
    if (!self::isConfigured() || !function_exists('curl_init')) {
      return 0;
    }

    $tokens = VoipPushToken::where('user_id', $userId)->where('is_active', true)->get();
    $sent = 0;
    foreach ($tokens as $token) {
      if (self::sendToToken($token, $payload)) {
        $sent++;
      }
    }
    return $sent;
  }

  private static function sendToToken(VoipPushToken $token, array $payload): bool
  {
    // Try the environment that worked last time first; a token from a
    // development build only works on the sandbox host and vice versa.
    $order = $token->apns_environment === 'sandbox'
      ? ['sandbox', 'production']
      : ['production', 'sandbox'];

    foreach ($order as $env) {
      [$status, $reason] = self::post($env, $token->voip_token, $payload);

      if ($status === 200) {
        $token->forceFill([
          'apns_environment' => $env,
          'last_used_at' => now(),
        ])->save();
        return true;
      }

      if ($status === 400 && $reason === 'BadDeviceToken') {
        continue; // wrong environment - try the other host
      }

      if ($status === 410 || in_array($reason, ['Unregistered', 'DeviceTokenNotForTopic'], true)) {
        $token->forceFill(['is_active' => false])->save();
        return false;
      }

      if ($status === 403 && $reason === 'ExpiredProviderToken') {
        Cache::forget(self::jwtCacheKey());
      }

      Log::warning('APNs VoIP push failed', [
        'token_id' => $token->id,
        'environment' => $env,
        'status' => $status,
        'reason' => $reason,
      ]);
      return false;
    }

    // BadDeviceToken on both hosts: the token is no good.
    $token->forceFill(['is_active' => false])->save();
    return false;
  }

  /** @return array{0:int,1:?string} HTTP status and APNs reason. */
  private static function post(string $env, string $deviceToken, array $payload): array
  {
    $jwt = self::jwt();
    if ($jwt === null) {
      return [0, 'NoProviderToken'];
    }

    $ch = curl_init(self::HOSTS[$env] . '/3/device/' . $deviceToken);
    curl_setopt_array($ch, [
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_HTTPHEADER => [
        'authorization: bearer ' . $jwt,
        'apns-topic: ' . config('services.apns.bundle_id') . '.voip',
        'apns-push-type: voip',
        'apns-priority: 10',
        // A call that cannot be delivered now is useless later.
        'apns-expiration: 0',
        'content-type: application/json',
      ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
      Log::warning('APNs VoIP request error', ['environment' => $env, 'error' => $error]);
      return [0, null];
    }
    $reason = null;
    if ($status !== 200 && is_string($body) && $body !== '') {
      $decoded = json_decode($body, true);
      $reason = is_array($decoded) ? ($decoded['reason'] ?? null) : null;
    }
    return [$status, $reason];
  }

  private static function jwtCacheKey(): string
  {
    return 'apns_provider_jwt:' . config('services.apns.key_id');
  }

  /**
   * APNs provider token (ES256 JWT). Apple wants it reused for up to an hour
   * and refuses one refreshed more than every 20 minutes, so it is cached 50.
   */
  private static function jwt(): ?string
  {
    return Cache::remember(self::jwtCacheKey(), now()->addMinutes(50), function () {
      $key = openssl_pkey_get_private((string) self::privateKey());
      if ($key === false) {
        Log::error('APNs private key could not be read');
        return null;
      }
      $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
      $header = $b64(json_encode(['alg' => 'ES256', 'kid' => config('services.apns.key_id')]));
      $claims = $b64(json_encode(['iss' => config('services.apns.team_id'), 'iat' => time()]));
      $signature = '';
      if (!openssl_sign("{$header}.{$claims}", $signature, $key, OPENSSL_ALGO_SHA256)) {
        Log::error('APNs JWT signing failed');
        return null;
      }
      return "{$header}.{$claims}." . $b64(self::derToRawSignature($signature));
    }) ?: null;
  }

  /** openssl gives an ASN.1 DER signature; JWT ES256 wants r||s, 32 bytes each. */
  private static function derToRawSignature(string $der): string
  {
    $offset = 2; // SEQUENCE tag + length (always short form for P-256)
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
      $offset++; // INTEGER tag
      $len = ord($der[$offset]);
      $offset++;
      $int = substr($der, $offset, $len);
      $offset += $len;
      $int = ltrim($int, "\x00");
      $parts[] = str_pad($int, 32, "\x00", STR_PAD_LEFT);
    }
    return $parts[0] . $parts[1];
  }

  private static function privateKey(): ?string
  {
    $inline = config('services.apns.private_key');
    if (!empty($inline)) {
      // .env cannot hold real newlines easily; accept "\n" escapes.
      return str_replace('\n', "\n", $inline);
    }
    $path = config('services.apns.private_key_path');
    if (!empty($path)) {
      $full = str_starts_with($path, '/') ? $path : base_path($path);
      if (is_readable($full)) {
        return file_get_contents($full) ?: null;
      }
    }
    return null;
  }
}
