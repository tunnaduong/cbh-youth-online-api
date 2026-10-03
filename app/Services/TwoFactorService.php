<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\TwoFactorTrustedDevice;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Two-factor authentication: login challenges, emailed codes, recovery
 * codes and trusted devices. Two methods are supported - an authenticator
 * app (TOTP, see TotpService) and a 6-digit code emailed to the account.
 */
class TwoFactorService
{
  public const METHOD_TOTP = 'totp';
  public const METHOD_EMAIL = 'email';

  public const ISSUER = 'CBH Youth Online';

  // A login challenge (the step between "password OK" and "token issued").
  public const CHALLENGE_TTL = 600;
  public const CHALLENGE_MAX_ATTEMPTS = 5;

  // Emailed codes.
  public const EMAIL_CODE_TTL = 600;
  public const EMAIL_CODE_MAX_ATTEMPTS = 5;
  public const EMAIL_RESEND_COOLDOWN = 60;

  // Wrong codes allowed per account before verification is locked for a
  // while - a challenge only allows a few tries, but a new one can be
  // started with the password, so this is what actually stops guessing.
  public const MAX_FAILURES = 10;
  public const FAILURE_LOCK_SECONDS = 900;

  public const RECOVERY_CODE_COUNT = 8;
  public const TRUSTED_DEVICE_DAYS = 60;

  /**
   * If this login needs a second step, start a challenge and return the
   * payload to send back instead of a token. Null means: go ahead and log in
   * (2FA is off, or the request comes from a device the user trusted).
   */
  public static function challengeFor(AuthAccount $user, ?string $deviceToken = null): ?array
  {
    if (!$user->hasTwoFactorEnabled()) {
      return null;
    }

    if ($deviceToken && self::isTrustedDevice($user, $deviceToken)) {
      return null;
    }

    $token = Str::random(64);
    Cache::put(self::challengeKey($token), [
      'user_id' => $user->id,
      'attempts' => 0,
      'expires_at' => now()->addSeconds(self::CHALLENGE_TTL)->getTimestamp(),
    ], self::CHALLENGE_TTL);

    $isEmail = $user->two_factor_method === self::METHOD_EMAIL;

    // Logging in again within the resend cooldown reuses the code already
    // sitting in the inbox instead of sending another one.
    if ($isEmail && !(self::emailCooldown($user, 'login') > 0 && Cache::has(self::emailCodeKey($user, 'login')))) {
      try {
        self::sendEmailCode($user, 'login');
      } catch (\Throwable $e) {
        // Don't fail the login over a mail hiccup - the client can ask to resend.
        Log::warning('Failed to send two-factor login code', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'two_factor_required' => true,
      'challenge_token' => $token,
      'method' => $user->two_factor_method,
      'email' => $isEmail ? self::maskEmail($user->email) : null,
      'expires_in' => self::CHALLENGE_TTL,
    ];
  }

  /**
   * The account a pending challenge belongs to, or null if the challenge
   * expired, was used, or ran out of attempts.
   */
  public static function challengeUser(string $token): ?AuthAccount
  {
    $challenge = Cache::get(self::challengeKey($token));

    return $challenge ? AuthAccount::find($challenge['user_id']) : null;
  }

  public static function recordChallengeFailure(string $token): void
  {
    $key = self::challengeKey($token);
    $challenge = Cache::get($key);
    if (!$challenge) {
      return;
    }

    $challenge['attempts']++;

    if ($challenge['attempts'] >= self::CHALLENGE_MAX_ATTEMPTS) {
      Cache::forget($key);
    } else {
      Cache::put($key, $challenge, Carbon::createFromTimestamp($challenge['expires_at']));
    }
  }

  public static function forgetChallenge(string $token): void
  {
    Cache::forget(self::challengeKey($token));
  }

  /**
   * Check a code from the user's 2FA method (authenticator app or email).
   */
  public static function verifyCode(AuthAccount $user, string $code, string $purpose = 'login'): bool
  {
    $code = preg_replace('/\s+/', '', $code);

    if ($user->two_factor_method === self::METHOD_TOTP) {
      $step = TotpService::verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
      if ($step === null) {
        return false;
      }

      $user->two_factor_last_step = $step;
      $user->save();

      return true;
    }

    if ($user->two_factor_method === self::METHOD_EMAIL) {
      return self::verifyEmailCode($user, $code, $purpose);
    }

    return false;
  }

  /**
   * Check a code that may be either a regular 2FA code or a recovery code.
   * Returns 'code', 'recovery' or null. A recovery code is consumed.
   */
  public static function verifyAny(AuthAccount $user, string $code, string $purpose = 'login'): ?string
  {
    if (self::verifyCode($user, $code, $purpose)) {
      return 'code';
    }

    if (self::verifyRecoveryCode($user, $code)) {
      return 'recovery';
    }

    return null;
  }

  /**
   * Email a fresh 6-digit code, replacing any earlier one for this purpose
   * ('login' for the login challenge, 'manage' for settings changes).
   */
  public static function sendEmailCode(AuthAccount $user, string $purpose): void
  {
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    Cache::put(self::emailCodeKey($user, $purpose), [
      'hash' => self::hashEmailCode($code),
      'attempts' => 0,
      'expires_at' => now()->addSeconds(self::EMAIL_CODE_TTL)->getTimestamp(),
    ], self::EMAIL_CODE_TTL);

    RateLimiter::hit(self::emailCooldownKey($user, $purpose), self::EMAIL_RESEND_COOLDOWN);

    $user->notify(new TwoFactorCode($code, intdiv(self::EMAIL_CODE_TTL, 60)));
  }

  /**
   * Seconds until another code may be emailed (0 = can send now).
   */
  public static function emailCooldown(AuthAccount $user, string $purpose): int
  {
    $key = self::emailCooldownKey($user, $purpose);

    return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
  }

  public static function verifyEmailCode(AuthAccount $user, string $code, string $purpose): bool
  {
    $key = self::emailCodeKey($user, $purpose);
    $pending = Cache::get($key);
    if (!$pending) {
      return false;
    }

    if (hash_equals($pending['hash'], self::hashEmailCode($code))) {
      Cache::forget($key);
      return true;
    }

    $pending['attempts']++;

    if ($pending['attempts'] >= self::EMAIL_CODE_MAX_ATTEMPTS) {
      Cache::forget($key);
    } else {
      Cache::put($key, $pending, Carbon::createFromTimestamp($pending['expires_at']));
    }

    return false;
  }

  /**
   * Replace the account's recovery codes. Returns the plain codes - the only
   * time they are available, only their hashes are stored.
   */
  public static function generateRecoveryCodes(AuthAccount $user): array
  {
    $codes = [];
    for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
      $codes[] = strtolower(Str::random(5) . '-' . Str::random(5));
    }

    $user->two_factor_recovery_codes = array_map(fn($code) => self::hashRecoveryCode($code), $codes);
    $user->save();

    return $codes;
  }

  /**
   * Check a recovery code and, if it is valid, use it up.
   */
  public static function verifyRecoveryCode(AuthAccount $user, string $code): bool
  {
    $hashes = $user->two_factor_recovery_codes ?? [];
    $index = array_search(self::hashRecoveryCode($code), $hashes, true);
    if ($index === false) {
      return false;
    }

    unset($hashes[$index]);
    $user->two_factor_recovery_codes = array_values($hashes);
    $user->save();

    return true;
  }

  /**
   * Remember the device that just passed the challenge. Returns the token
   * the client keeps and sends as `device_token` on later logins.
   *
   * A device that already holds a token (from another account signed in on
   * it) passes it as $existingToken and keeps using that one, so a single
   * stored token works for every account on the device.
   */
  public static function trustDevice(AuthAccount $user, ?string $name = null, ?string $existingToken = null): string
  {
    $token = $existingToken && preg_match('/^[A-Za-z0-9]{64}$/', $existingToken)
      ? $existingToken
      : Str::random(64);

    TwoFactorTrustedDevice::updateOrCreate(
      ['user_id' => $user->id, 'token_hash' => hash('sha256', $token)],
      [
        'device_name' => $name ? Str::limit($name, 250, '') : null,
        'last_used_at' => now(),
        'expires_at' => now()->addDays(self::TRUSTED_DEVICE_DAYS),
      ]
    );

    return $token;
  }

  public static function isTrustedDevice(AuthAccount $user, string $token): bool
  {
    $device = TwoFactorTrustedDevice::where('user_id', $user->id)
      ->where('token_hash', hash('sha256', $token))
      ->where('expires_at', '>', now())
      ->first();

    if (!$device) {
      return false;
    }

    $device->update(['last_used_at' => now()]);

    return true;
  }

  /**
   * Make every device go through the challenge again (also called when the
   * password changes, since a trusted device plus the password is a full login).
   */
  public static function forgetTrustedDevices(int $userId): int
  {
    return TwoFactorTrustedDevice::where('user_id', $userId)->delete();
  }

  public static function disable(AuthAccount $user): void
  {
    $user->two_factor_method = null;
    $user->two_factor_secret = null;
    $user->two_factor_recovery_codes = null;
    $user->two_factor_confirmed_at = null;
    $user->two_factor_last_step = null;
    $user->save();

    self::forgetTrustedDevices($user->id);
  }

  public static function tooManyFailures(AuthAccount $user): bool
  {
    return RateLimiter::tooManyAttempts(self::failureKey($user), self::MAX_FAILURES);
  }

  /**
   * Minutes until verification is unlocked again after too many wrong codes.
   */
  public static function failureLockMinutes(AuthAccount $user): int
  {
    return (int) ceil(RateLimiter::availableIn(self::failureKey($user)) / 60);
  }

  public static function hitFailure(AuthAccount $user): void
  {
    RateLimiter::hit(self::failureKey($user), self::FAILURE_LOCK_SECONDS);
  }

  public static function clearFailures(AuthAccount $user): void
  {
    RateLimiter::clear(self::failureKey($user));
  }

  /**
   * tunnaduong@gmail.com -> tu*******@gmail.com
   */
  public static function maskEmail(?string $email): ?string
  {
    if (!$email) {
      return null;
    }

    return preg_replace('/(?<=.{2}).(?=[^@]*@)/u', '*', $email);
  }

  private static function challengeKey(string $token): string
  {
    return 'two_factor:challenge:' . hash('sha256', $token);
  }

  private static function emailCodeKey(AuthAccount $user, string $purpose): string
  {
    return 'two_factor:email_code:' . $purpose . ':' . $user->id;
  }

  private static function emailCooldownKey(AuthAccount $user, string $purpose): string
  {
    return 'two_factor:email_cooldown:' . $purpose . ':' . $user->id;
  }

  private static function failureKey(AuthAccount $user): string
  {
    return 'two_factor:failures:' . $user->id;
  }

  private static function hashEmailCode(string $code): string
  {
    return hash_hmac('sha256', $code, (string) config('app.key'));
  }

  private static function hashRecoveryCode(string $code): string
  {
    return hash('sha256', strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code)));
  }
}
