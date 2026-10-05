<?php

namespace App\Services;

use App\Events\LoginApprovalRequested;
use App\Models\AuthAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Two-factor by approval on a device that is already logged in (the
 * "device" method), the way GitHub Mobile and Facebook do it:
 *
 *   1. the device logging in asks for an approval and is shown a two-digit
 *      number;
 *   2. the account's other logged-in devices are told (push notification and
 *      a realtime event) and show the request with three numbers;
 *   3. the user picks the number shown on the new device and approves - or
 *      denies;
 *   4. the new device, which has been polling, is logged in.
 *
 * Picking the number is what stops someone approving a request they did not
 * start just to make the notifications stop.
 *
 * Everything lives in Cache for a few minutes, like the login challenges it
 * belongs to (see TwoFactorService): nothing here outlives the login.
 */
class LoginApprovalService
{
  public const TTL = 300;
  // New requests per login challenge (each one notifies every device).
  private const MAX_REQUESTS_PER_CHALLENGE = 5;

  public const PENDING = 'pending';
  public const APPROVED = 'approved';
  public const DENIED = 'denied';

  /**
   * Start (or restart) the approval for a login challenge and tell the
   * account's logged-in devices. Returns the number to show on the device
   * logging in, or null when this challenge asked too many times.
   */
  public static function start(AuthAccount $user, string $challengeToken, Request $request): ?array
  {
    $challengeKey = self::challengeKey($challengeToken);
    $previous = Cache::get($challengeKey);

    $requests = ($previous['requests'] ?? 0) + 1;
    if ($requests > self::MAX_REQUESTS_PER_CHALLENGE) {
      return null;
    }

    // One live request per challenge: asking again replaces the old one.
    if (!empty($previous['id'])) {
      self::forget($user->id, $previous['id']);
    }

    $number = random_int(10, 99);
    $numbers = [$number];
    while (count($numbers) < 3) {
      $decoy = random_int(10, 99);
      if (!in_array($decoy, $numbers, true)) {
        $numbers[] = $decoy;
      }
    }
    shuffle($numbers);

    $id = Str::random(40);
    $details = DeviceSessionService::detailsFromRequest($request);
    $expiresAt = now()->addSeconds(self::TTL);

    Cache::put(self::approvalKey($id), [
      'id' => $id,
      'user_id' => $user->id,
      'number' => $number,
      'numbers' => $numbers,
      'status' => self::PENDING,
      'platform' => $details['platform'] ?? null,
      'device_name' => $details['device_name'] ?? Str::limit((string) $request->userAgent(), 120, ''),
      'device_model' => $details['device_model'] ?? null,
      'ip' => $request->ip(),
      'created_at' => now()->toIso8601String(),
      'expires_at' => $expiresAt->toIso8601String(),
    ], self::TTL);

    Cache::put($challengeKey, ['id' => $id, 'requests' => $requests], TwoFactorService::CHALLENGE_TTL);

    $ids = array_values(array_filter(
      Cache::get(self::userKey($user->id), []),
      fn($existing) => Cache::has(self::approvalKey($existing))
    ));
    $ids[] = $id;
    Cache::put(self::userKey($user->id), $ids, self::TTL);

    self::notify($user, $id);

    return [
      'number' => $number,
      'expires_in' => self::TTL,
    ];
  }

  /**
   * The account's requests still waiting for an answer, for its logged-in
   * devices. The right number is not included - only the three to pick from.
   */
  public static function pendingFor(AuthAccount $user): array
  {
    $pending = [];

    foreach (Cache::get(self::userKey($user->id), []) as $id) {
      $approval = Cache::get(self::approvalKey($id));
      if (!$approval || $approval['status'] !== self::PENDING || (int) $approval['user_id'] !== (int) $user->id) {
        continue;
      }

      $pending[] = [
        'id' => $approval['id'],
        'numbers' => $approval['numbers'],
        'platform' => $approval['platform'],
        'device_name' => $approval['device_name'],
        'device_model' => $approval['device_model'],
        'ip' => $approval['ip'],
        'created_at' => $approval['created_at'],
        'expires_at' => $approval['expires_at'],
      ];
    }

    return $pending;
  }

  /**
   * Answer a request from a logged-in device. Approving needs the number
   * shown on the device logging in; a wrong number denies the request, as a
   * guess is not an approval.
   *
   * @return string  'approved', 'denied', 'wrong_number' or 'not_found'
   */
  public static function respond(AuthAccount $user, string $id, bool $approve, ?int $number): string
  {
    $key = self::approvalKey($id);
    $approval = Cache::get($key);

    if (!$approval || (int) $approval['user_id'] !== (int) $user->id || $approval['status'] !== self::PENDING) {
      return 'not_found';
    }

    $wrongNumber = $approve && $number !== (int) $approval['number'];
    $approval['status'] = $approve && !$wrongNumber ? self::APPROVED : self::DENIED;

    // Kept until the device logging in has read the answer (or it expires).
    Cache::put($key, $approval, self::TTL);

    if ($wrongNumber) {
      return 'wrong_number';
    }

    return $approval['status'];
  }

  /**
   * Where the approval of a login challenge stands, for the device logging
   * in: 'pending', 'approved', 'denied', or 'expired' when there is none
   * (never asked, or timed out).
   */
  public static function statusForChallenge(string $challengeToken): string
  {
    $link = Cache::get(self::challengeKey($challengeToken));
    $approval = !empty($link['id']) ? Cache::get(self::approvalKey($link['id'])) : null;

    return $approval['status'] ?? 'expired';
  }

  /**
   * Use up an approved request: true exactly once per approval, so one
   * approval can't log two requests in.
   */
  public static function consume(string $challengeToken): bool
  {
    $link = Cache::pull(self::challengeKey($challengeToken));
    if (empty($link['id'])) {
      return false;
    }

    $approval = Cache::pull(self::approvalKey($link['id']));

    return $approval && $approval['status'] === self::APPROVED;
  }

  /**
   * Push + realtime: every device the account is logged in on is told there
   * is a login to look at. Never fails the login request.
   */
  private static function notify(AuthAccount $user, string $id): void
  {
    $title = 'Yêu cầu đăng nhập mới';
    $body = 'Có thiết bị đang đăng nhập vào tài khoản của bạn. Nhấn để xem và xác nhận.';
    $data = [
      'type' => 'login_approval',
      'approval_id' => $id,
      'url' => '/settings?tab=account&approval=' . $id,
    ];

    try {
      broadcast(new LoginApprovalRequested($user->id, $id));
    } catch (\Throwable $e) {
      Log::warning('Login approval broadcast failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
    }

    try {
      PushNotificationService::broadcastExpoPush([$user->id], $title, $body, $data);
    } catch (\Throwable $e) {
      Log::warning('Login approval Expo push failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
    }

    try {
      PushNotificationService::broadcastWebPush([$user->id], $title, $body, $data);
    } catch (\Throwable $e) {
      Log::warning('Login approval web push failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
    }
  }

  private static function forget(int $userId, string $id): void
  {
    Cache::forget(self::approvalKey($id));
    Cache::put(
      self::userKey($userId),
      array_values(array_diff(Cache::get(self::userKey($userId), []), [$id])),
      self::TTL
    );
  }

  private static function approvalKey(string $id): string
  {
    return 'login_approval:' . hash('sha256', $id);
  }

  private static function challengeKey(string $challengeToken): string
  {
    return 'login_approval_challenge:' . hash('sha256', $challengeToken);
  }

  private static function userKey(int $userId): string
  {
    return 'login_approvals_user:' . $userId;
  }
}
