<?php

namespace App\Services;

use App\Models\AuthAccount;
use App\Models\KnownDevice;
use App\Notifications\NewDeviceLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Describes the device behind a request (from the X-Client-* / X-Device-*
 * headers the web and mobile clients send) and keeps track of which devices
 * an account has logged in from.
 */
class DeviceSessionService
{
  private const PLATFORMS = ['web', 'ios', 'android'];

  /**
   * The device details a request carries. Only keys the client actually
   * sent are present, so this can be merged over stored values.
   */
  public static function detailsFromRequest(Request $request): array
  {
    $platform = strtolower((string) self::header($request, 'X-Client-Platform', 20));

    return array_filter([
      'platform' => in_array($platform, self::PLATFORMS, true) ? $platform : null,
      'app_version' => self::header($request, 'X-Client-Version', 50),
      'device_name' => self::header($request, 'X-Device-Name', 255),
      'device_model' => self::header($request, 'X-Device-Model', 100),
    ], fn($value) => $value !== null);
  }

  /**
   * Called right after a login issued $token: stores the device details on
   * the token, and emails the owner if the account has never logged in from
   * this device before.
   *
   * @param  bool  $notify  False for a brand-new account's first login.
   */
  /**
   * Note how a login was made (password, google, facebook, apple, passkey,
   * register, app) and whether it went through two-factor, for the
   * "logged-in devices" list. Never fails the login - also not when the
   * columns don't exist yet (deployed before `php artisan migrate`).
   */
  public static function recordLoginMethod(PersonalAccessToken $token, string $method, bool $twoFactor = false): void
  {
    try {
      $token->forceFill([
        'login_method' => $method,
        'login_two_factor' => $twoFactor,
      ])->save();
    } catch (Throwable $e) {
      Log::warning('Could not record the login method', ['error' => $e->getMessage()]);
    }
  }

  public static function recordLogin(AuthAccount $user, PersonalAccessToken $token, Request $request, bool $notify = true): void
  {
    try {
      $details = self::detailsFromRequest($request);

      // App versions from before the headers existed: the user agent is the
      // only hint about what the device is.
      if (!isset($details['device_name']) && $request->userAgent()) {
        $details['device_name'] = Str::limit($request->userAgent(), 250, '');
      }

      $token->forceFill($details)->save();

      $device = KnownDevice::firstOrNew([
        'user_id' => $user->id,
        'fingerprint' => self::fingerprint($details),
      ]);
      $isNew = !$device->exists;

      $device->fill([
        'platform' => $details['platform'] ?? null,
        'device_name' => $details['device_name'] ?? null,
        'device_model' => $details['device_model'] ?? null,
        'last_login_at' => now(),
      ])->save();

      if ($isNew && $notify && $user->email) {
        $user->notify(new NewDeviceLogin($details, now()));
      }
    } catch (\Throwable $e) {
      // Bookkeeping and a courtesy email must never break a login.
      Log::warning('Failed to record device login', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Identifies a device by platform, name and model. The version at the end
   * of a browser name ("Chrome 129") is dropped, otherwise every browser
   * update would look like a new device.
   */
  private static function fingerprint(array $details): string
  {
    $model = preg_replace('/\s+\d+(\.\d+)*$/', '', $details['device_model'] ?? '');

    return hash('sha256', implode('|', [
      $details['platform'] ?? '',
      Str::lower($details['device_name'] ?? ''),
      Str::lower($model),
    ]));
  }

  /**
   * Header values are URL-encoded by the clients, since device names can
   * contain characters that are not allowed in a raw HTTP header.
   */
  private static function header(Request $request, string $name, int $maxLength): ?string
  {
    $value = $request->header($name);
    if (!is_string($value)) {
      return null;
    }

    $value = trim(rawurldecode($value));

    return $value === '' ? null : Str::limit($value, $maxLength, '');
  }
}
