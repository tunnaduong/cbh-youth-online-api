<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\AuthAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the audit log (cyo_audit_logs) for what users DO: every request
 * that changes something - post, comment, vote, follow, settings, orders,
 * admin actions... - plus logging in and out. One place, so a new endpoint
 * is logged without anyone having to remember it.
 *
 * What is recorded: who, the action (a readable name from NAMES, or one
 * built from the route), what it was done to (the id in the URL, or the id
 * the API answered with), and the submitted fields - never secrets, files,
 * or the text of private messages.
 *
 * What is left out: reading (GET), and technical traffic that is not
 * something a person did (heartbeats, view counters, read receipts, polling,
 * webhooks).
 *
 * Profile and post edits are logged elsewhere with the data before and
 * after (model events in AppServiceProvider), so they are skipped here.
 */
class RecordAuditLog
{
  // Not actions of a person: pings, counters, polling, previews, webhooks,
  // uploads of a file that the real action then refers to.
  private const SKIP = '#(online-status|online-users/track|/heartbeat$|/views?$|/read(-all)?$|/options$'
    . '|approval/status$|two-factor/resend$|email/send$|topics/preview$|sepay|web-session/handoff$'
    . '|subscribe$|unregister$|expo-token|shop/cart$|(^|/)upload$|oauth/exchange$|broadcasting/)#';

  // Logged elsewhere with more detail (model events, or the controller).
  private const LOGGED_BY_MODEL = [
    'PUT users/{username}/profile',
    'PUT topics/{id}',
    // Logged by the controller, against the account they were done to.
    'POST admin/users/{id}/reset-password',
    'POST admin/users/{id}/reset-two-factor',
  ];

  // Logged even without a signed-in user, and also when they fail.
  private const AUTH_ROUTES = [
    'POST login' => 'LOGIN',
    'POST login/oauth' => 'LOGIN_SOCIAL',
    'POST login/passkey' => 'LOGIN_PASSKEY',
    'POST login/passkey/redeem' => 'LOGIN_PASSKEY',
    'POST login/two-factor' => 'LOGIN_TWO_FACTOR',
    'POST login/two-factor/approval' => 'LOGIN_APPROVAL_REQUEST',
    'POST web-session/redeem' => 'LOGIN_WEB_HANDOFF',
    'POST register' => 'REGISTER',
    'POST password/email' => 'REQUEST_PASSWORD_RESET',
    'POST password/reset' => 'RESET_PASSWORD',
  ];

  // Readable names for the common actions. Anything else is named from its
  // route: "DELETE shop/products/{id}" -> DELETE_SHOP_PRODUCTS.
  private const NAMES = [
    'POST logout' => 'LOGOUT',
    'POST password/change' => 'CHANGE_PASSWORD',
    'POST user/delete-account' => 'DELETE_ACCOUNT',
    'POST topics' => 'CREATE_POST',
    'DELETE topics/{id}' => 'DELETE_POST',
    'POST topics/{id}/archive' => 'ARCHIVE_POST',
    'DELETE topics/{id}/archive' => 'UNARCHIVE_POST',
    'POST topics/{id}/votes' => 'VOTE_POST',
    'DELETE topics/{id}/votes' => 'UNVOTE_POST',
    'POST topics/{id}/comments' => 'CREATE_COMMENT',
    'PUT comments/{id}' => 'EDIT_COMMENT',
    'DELETE comments/{id}' => 'DELETE_COMMENT',
    'POST comments/{id}/votes' => 'VOTE_COMMENT',
    'DELETE comments/{id}/votes' => 'UNVOTE_COMMENT',
    'POST user/saved-topics' => 'SAVE_POST',
    'DELETE user/saved-topics/{id}' => 'UNSAVE_POST',
    'POST user/hidden-topics' => 'HIDE_POST',
    'DELETE user/hidden-topics/{id}' => 'UNHIDE_POST',
    'POST users/{username}/follow' => 'FOLLOW_USER',
    'DELETE users/{username}/unfollow' => 'UNFOLLOW_USER',
    'POST users/block' => 'BLOCK_USER',
    'POST users/unblock' => 'UNBLOCK_USER',
    'POST users/{username}/avatar' => 'UPDATE_AVATAR',
    'POST users/{username}/cover' => 'UPDATE_COVER',
    'POST stories' => 'CREATE_STORY',
    'DELETE stories/{story}' => 'DELETE_STORY',
    'POST stories/{story}/react' => 'REACT_STORY',
    'DELETE stories/{story}/react' => 'UNREACT_STORY',
    'POST stories/{story}/reply' => 'REPLY_STORY',
    'POST two-factor/totp' => 'SETUP_TWO_FACTOR',
    'POST two-factor/email' => 'SETUP_TWO_FACTOR',
    'POST two-factor/device' => 'ENABLE_TWO_FACTOR',
    'POST two-factor/confirm' => 'ENABLE_TWO_FACTOR',
    'POST two-factor/disable' => 'DISABLE_TWO_FACTOR',
    'POST two-factor/recovery-codes' => 'REGENERATE_RECOVERY_CODES',
    'POST two-factor/approvals/{id}' => 'ANSWER_LOGIN_APPROVAL',
    'PUT two-factor/social-login' => 'SET_TWO_FACTOR_SOCIAL_LOGIN',
    'DELETE two-factor/trusted-devices' => 'FORGET_TRUSTED_DEVICES',
    'POST passkeys' => 'ADD_PASSKEY',
    'DELETE passkeys/{id}' => 'REMOVE_PASSKEY',
    'DELETE sessions/{id}' => 'LOGOUT_DEVICE',
    'DELETE sessions' => 'LOGOUT_OTHER_DEVICES',
    'POST points/gift' => 'GIFT_POINTS',
    'POST checkin' => 'DAILY_CHECKIN',
    'POST wallet/deposit-request' => 'REQUEST_DEPOSIT',
    'POST wallet/withdrawal-request' => 'REQUEST_WITHDRAWAL',
    'POST shop/orders' => 'CREATE_ORDER',
    'POST shop/orders/{id}/cancel' => 'CANCEL_ORDER',
    'POST study-materials' => 'CREATE_STUDY_MATERIAL',
    'POST study-materials/{id}/purchase' => 'PURCHASE_STUDY_MATERIAL',
    'POST study-materials/{id}/ratings' => 'RATE_STUDY_MATERIAL',
    'POST student-verification' => 'SUBMIT_STUDENT_VERIFICATION',
    'POST feedback' => 'REPORT_BUG',
    'PUT notification-settings' => 'UPDATE_NOTIFICATION_SETTINGS',
  ];

  // Request fields that never go into the log.
  private const SECRET_KEYS = '/password|token|secret|code|otp|credential|verifier|signature|challenge|cvv|card/i';

  private const MAX_TEXT = 1000;

  public function handle(Request $request, Closure $next)
  {
    $response = $next($request);

    try {
      $this->record($request, $response);
    } catch (\Throwable $e) {
      // Logging must never fail the request it describes.
      Log::warning('Audit log middleware failed', ['error' => $e->getMessage()]);
    }

    return $response;
  }

  private function record(Request $request, $response): void
  {
    if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || !$request->route()) {
      return;
    }

    $uri = preg_replace('#^v1\.0/#', '', $request->route()->uri());
    $key = $request->method() . ' ' . $uri;

    if (preg_match(self::SKIP, $uri) || in_array($key, self::LOGGED_BY_MODEL, true)) {
      return;
    }

    $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
    $ok = $status >= 200 && $status < 300;
    $body = $this->responseData($response);
    $isAuthRoute = isset(self::AUTH_ROUTES[$key]);

    $userId = Auth::id();

    if ($isAuthRoute) {
      // A login has no signed-in user yet: take it from the answer, or from
      // the name that was typed.
      $userId = $userId ?? ($body['user']['id'] ?? null) ?? $this->accountIdFromInput($request);

      // "Needs a second step" is not a login yet; the second step is logged.
      if ($ok && !empty($body['two_factor_required'])) {
        return;
      }

      AuditLog::record(self::AUTH_ROUTES[$key] . ($ok ? '' : '_FAILED'), $userId, [
        'actor_id' => null,
        'new' => array_filter([
          'username' => $this->short($request->input('username') ?? $request->input('email')),
          'provider' => $this->short($request->input('provider')),
          'status' => $ok ? null : $status,
        ], fn($value) => $value !== null),
        'target_type' => 'account',
        'target_id' => $userId,
      ]);

      return;
    }

    // Everything else: only what a signed-in user did, and only when it
    // went through.
    if (!$userId || !$ok) {
      return;
    }

    [$targetType, $targetId] = $this->target($request, $uri, $body);

    AuditLog::record($this->name($key, $uri, $request->method()), $userId, [
      'actor_id' => null,
      'new' => $this->payload($request, $uri),
      'target_type' => $targetType,
      'target_id' => $targetId,
    ]);
  }

  private function name(string $key, string $uri, string $method): string
  {
    if (isset(self::NAMES[$key])) {
      return self::NAMES[$key];
    }

    // "admin/users/{id}/ban" -> ADMIN_USERS_BAN
    $words = preg_replace('#\{[^}]+\}#', '', $uri);
    $words = strtoupper(trim(preg_replace('#[^A-Za-z0-9]+#', '_', $words), '_'));

    return Str::limit($method . '_' . ($words !== '' ? $words : 'ROOT'), 50, '');
  }

  /**
   * What the action was done to: the first id in the URL (with the path
   * segment before it as its type), or the id of what was just created.
   */
  private function target(Request $request, string $uri, array $body): array
  {
    $segments = explode('/', $uri);

    foreach ($segments as $index => $segment) {
      if (!preg_match('#^\{(\w+)\??\}$#', $segment, $match)) {
        continue;
      }

      $value = $request->route($match[1]);
      $value = is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value;
      $type = Str::singular($segments[$index - 1] ?? 'item');

      if (is_numeric($value)) {
        return [Str::limit($type, 40, ''), (int) $value];
      }

      // A username in the URL: the account it belongs to.
      if (is_string($value) && $match[1] === 'username') {
        return ['account', AuthAccount::where('username', $value)->value('id')];
      }
    }

    $createdId = $body['id'] ?? $body['data']['id'] ?? null;

    return [
      Str::limit(Str::singular(preg_replace('#[^a-z0-9]+#i', '_', $segments[count($segments) - 1] ?? 'item')), 40, ''),
      is_numeric($createdId) ? (int) $createdId : null,
    ];
  }

  /**
   * The submitted fields worth keeping: no secrets, no files, long text cut
   * short - and nothing at all from chat, whose messages are private.
   */
  private function payload(Request $request, string $uri): ?array
  {
    if (preg_match('#(^|/)(chat|messages|conversations|groups)(/|$)#', $uri)) {
      return null;
    }

    $data = [];
    foreach ($request->except(array_keys($request->allFiles())) as $field => $value) {
      if (preg_match(self::SECRET_KEYS, (string) $field)) {
        continue;
      }
      $data[$field] = is_string($value) ? $this->short($value) : $value;
    }

    // Nested values are kept only when they are small.
    $data = array_filter($data, fn($value) => !is_array($value) || strlen(json_encode($value)) <= self::MAX_TEXT);

    return $data ?: null;
  }

  private function short($value): ?string
  {
    return is_string($value) && $value !== '' ? Str::limit($value, self::MAX_TEXT) : null;
  }

  private function responseData($response): array
  {
    if (!method_exists($response, 'getContent')) {
      return [];
    }

    $content = $response->getContent();
    if (!is_string($content) || $content === '' || strlen($content) > 200000) {
      return [];
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
  }

  private function accountIdFromInput(Request $request): ?int
  {
    $name = $request->input('username') ?? $request->input('email');
    if (!is_string($name) || $name === '') {
      return null;
    }

    return AuthAccount::where('username', $name)->orWhere('email', $name)->value('id');
  }
}
