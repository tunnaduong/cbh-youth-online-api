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
  public const EMAIL_SETUP_TTL = 1800;

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
   *
   * $link: a social login matched to this account by email only -
   * ['provider' => ..., 'provider_id' => ...] to attach once the challenge is
   * passed (see challengeLink()), so later logins through that provider
   * need no second step.
   */
  public static function challengeFor(AuthAccount $user, ?string $deviceToken = null, ?array $link = null): ?array
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
      'link' => $link,
    ], self::CHALLENGE_TTL);

    $methods = self::enabledMethods($user);
    if (!$methods) {
      // Marked as on but with nothing usable (e.g. a missing secret): there
      // is no code the user could enter, so don't lock them out.
      return null;
    }
    $default = $methods[0];
    $emailEnabled = in_array(self::METHOD_EMAIL, $methods, true);

    // A code is only emailed up front when email is the method offered
    // first; with an authenticator app on as well, the client asks for the
    // email (resend endpoint) if the user picks that method instead.
    $emailSent = false;

    if ($default === self::METHOD_EMAIL) {
      $emailSent = true;

      // Logging in again within the resend cooldown reuses the code already
      // sitting in the inbox instead of sending another one.
      if (!(self::emailCooldown($user, 'login') > 0 && Cache::has(self::emailCodeKey($user, 'login')))) {
        try {
          self::sendEmailCode($user, 'login');
        } catch (\Throwable $e) {
          // Don't fail the login over a mail hiccup - the client can ask to resend.
          $emailSent = false;
          Log::warning('Failed to send two-factor login code', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
          ]);
        }
      }
    }

    return [
      'two_factor_required' => true,
      'challenge_token' => $token,
      // Every method the user may pick from, and the one to show first.
      'methods' => $methods,
      'method' => $default,
      'email' => $emailEnabled ? self::maskEmail($user->email) : null,
      'email_sent' => $emailSent,
      'expires_in' => self::CHALLENGE_TTL,
    ];
  }

  /**
   * The methods the account has confirmed, strongest first (the first one is
   * what a login offers by default).
   */
  public static function enabledMethods(AuthAccount $user): array
  {
    $methods = [];

    if ($user->two_factor_totp_confirmed_at !== null && $user->two_factor_secret) {
      $methods[] = self::METHOD_TOTP;
    }
    if ($user->two_factor_email_confirmed_at !== null) {
      $methods[] = self::METHOD_EMAIL;
    }

    return $methods;
  }

  public static function isMethodEnabled(AuthAccount $user, string $method): bool
  {
    return in_array($method, self::enabledMethods($user), true);
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

  /**
   * The social login waiting to be attached to the challenge's account
   * (['provider', 'provider_id']), if the challenge came from one.
   */
  public static function challengeLink(string $token): ?array
  {
    $link = Cache::get(self::challengeKey($token))['link'] ?? null;

    return is_array($link) && !empty($link['provider']) && !empty($link['provider_id']) ? $link : null;
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
   * Check a code against the account's confirmed methods - only $method
   * when the client says which one the user picked, otherwise each of them.
   */
  public static function verifyCode(AuthAccount $user, string $code, string $purpose = 'login', ?string $method = null): bool
  {
    $code = preg_replace('/\s+/', '', $code);
    $methods = self::enabledMethods($user);

    if ($method !== null) {
      $methods = array_values(array_intersect($methods, [$method]));
    }

    foreach ($methods as $candidate) {
      if ($candidate === self::METHOD_TOTP && self::verifyTotp($user, $code)) {
        return true;
      }
      if ($candidate === self::METHOD_EMAIL && self::verifyEmailCode($user, $code, $purpose)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check an authenticator-app code against the stored secret, whether or
   * not the method is confirmed yet (setup confirms it with this).
   */
  public static function verifyTotp(AuthAccount $user, string $code): bool
  {
    $step = TotpService::verify(
      (string) $user->two_factor_secret,
      preg_replace('/\s+/', '', $code),
      $user->two_factor_last_step
    );

    if ($step === null) {
      return false;
    }

    $user->two_factor_last_step = $step;
    $user->save();

    return true;
  }

  /**
   * Check a code that may be either a regular 2FA code or a recovery code.
   * Returns 'code', 'recovery' or null. A recovery code is consumed.
   */
  public static function verifyAny(AuthAccount $user, string $code, string $purpose = 'login', ?string $method = null): ?string
  {
    if (self::verifyCode($user, $code, $purpose, $method)) {
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

  /**
   * Turn every method off, including anything half set up.
   */
  public static function disable(AuthAccount $user): void
  {
    $user->two_factor_method = null;
    $user->two_factor_secret = null;
    $user->two_factor_recovery_codes = null;
    $user->two_factor_confirmed_at = null;
    $user->two_factor_totp_confirmed_at = null;
    $user->two_factor_email_confirmed_at = null;
    $user->two_factor_last_step = null;
    $user->save();

    self::forgetEmailSetup($user);
    self::forgetTrustedDevices($user->id);
  }

  /**
   * Mark one method as confirmed and working.
   */
  public static function enableMethod(AuthAccount $user, string $method): void
  {
    if ($method === self::METHOD_TOTP) {
      $user->two_factor_totp_confirmed_at = now();
    } else {
      $user->two_factor_email_confirmed_at = now();
      self::forgetEmailSetup($user);
    }

    self::syncSummaryColumns($user);
    $user->save();
  }

  /**
   * Turn one method off (or drop its unfinished setup). Turning the last
   * one off turns two-factor off altogether.
   */
  public static function disableMethod(AuthAccount $user, string $method): void
  {
    if ($method === self::METHOD_TOTP) {
      $user->two_factor_secret = null;
      $user->two_factor_totp_confirmed_at = null;
      $user->two_factor_last_step = null;
    } else {
      $user->two_factor_email_confirmed_at = null;
      self::forgetEmailSetup($user);
      Cache::forget(self::emailCodeKey($user, 'manage'));
    }

    if (!$user->hasTwoFactorEnabled()) {
      // Nothing confirmed is left. Keep the other method's unfinished setup
      // (if any) but drop what only makes sense while two-factor is on.
      $user->two_factor_recovery_codes = null;
      self::forgetTrustedDevices($user->id);
    }

    self::syncSummaryColumns($user);
    $user->save();
  }

  /**
   * An email setup is "pending" between asking for it and confirming the
   * code. There is nothing to store for it on the account, so a short-lived
   * flag remembers that the code may be re-sent and confirmed.
   */
  public static function startEmailSetup(AuthAccount $user): void
  {
    Cache::put(self::emailSetupKey($user), true, self::EMAIL_SETUP_TTL);
  }

  public static function hasEmailSetupPending(AuthAccount $user): bool
  {
    return Cache::has(self::emailSetupKey($user));
  }

  public static function forgetEmailSetup(AuthAccount $user): void
  {
    Cache::forget(self::emailSetupKey($user));
  }

  /**
   * `two_factor_method` (the default method) and `two_factor_confirmed_at`
   * ("any method is on") summarise the per-method columns for code that
   * only needs the overall state.
   */
  private static function syncSummaryColumns(AuthAccount $user): void
  {
    $methods = self::enabledMethods($user);

    $user->two_factor_method = $methods[0] ?? null;
    $user->two_factor_confirmed_at = $methods
      ? ($user->two_factor_confirmed_at ?? now())
      : null;
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

  private static function emailSetupKey(AuthAccount $user): string
  {
    return 'two_factor:email_setup:' . $user->id;
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
