<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\AuditLog;

/**
 * Controller providing mobile and web client dynamic application configuration,
 * including seasonal icons and forced/recommended icon overrides.
 */
class AppConfigController extends Controller
{
  /**
   * Return application configuration and launcher icon directives.
   *
   * GET /v1.0/app/config
   */
  public function index(Request $request)
  {
    $configFile = storage_path('app/app_config.json');
    $storedConfig = file_exists($configFile)
      ? json_decode((string) file_get_contents($configFile), true)
      : [];

    if (!is_array($storedConfig)) {
      $storedConfig = [];
    }

    // Determine seasonal icon based on current server date (Asia/Ho_Chi_Minh)
    $now = Carbon::now('Asia/Ho_Chi_Minh');
    $month = (int) $now->month;
    $day = (int) $now->day;

    $seasonalIcon = 'default';
    $seasonalReason = null;

    // 20/11 Vietnamese Teachers' Day: the week of 20/11 (Nov 14 to Nov 21)
    if ($month === 11 && $day >= 14 && $day <= 21) {
      $seasonalIcon = 'TeachersDay';
      $seasonalReason = "Kỷ niệm Ngày Nhà giáo Việt Nam 20/11";
    }
    // Christmas: around a week before holiday (Dec 18 to Dec 27)
    elseif ($month === 12 && $day >= 18 && $day <= 27) {
      $seasonalIcon = 'Christmas';
      $seasonalReason = "Mừng mùa lễ Giáng Sinh";
    }
    // New Year season: From Tết Dương Lịch (Dec 28) through Tết Âm Lịch (Lunar New Year) and Rằm tháng Giêng
    // Automatically selects NewYear2026 or NewYear2027 depending on the calendar cycle
    elseif (($month === 12 && $day >= 28) || $month === 1 || $month === 2 || ($now->year === 2026 && $month === 3 && $day <= 5) || ($now->year === 2027 && $month === 3 && $day <= 5)) {
      $is2027Season = ($now->year === 2026 && $month === 12) || ($now->year === 2027);
      $seasonalIcon = $is2027Season ? 'NewYear2027' : 'NewYear2026';
      $seasonalReason = $is2027Season
        ? "Chào đón Năm mới 2027 & Tết Đinh Mùi"
        : "Chào đón Năm mới 2026 & Tết Bính Ngọ";
    }

    // Admin override from storage/app/app_config.json if set
    $iconConfig = $storedConfig['app_icon'] ?? [];
    $forcedIcon = !empty($iconConfig['icon']) ? $iconConfig['icon'] : null;
    $isForce = !empty($iconConfig['force']);
    $overrideReason = $iconConfig['reason'] ?? null;

    // Active target icon: if admin set a specific icon, that takes precedence; otherwise seasonal
    $activeIcon = $forcedIcon ?: $seasonalIcon;
    $activeReason = $forcedIcon ? $overrideReason : $seasonalReason;

    // OTA update configuration
    $otaConfig = $storedConfig['ota_update'] ?? [];
    $isForceOta = !empty($otaConfig['force']);

    return response()->json([
      'app_icon' => [
        'target_icon' => $activeIcon,
        'force' => (bool) $isForce,
        'seasonal_icon' => $seasonalIcon,
        'seasonal_reason' => $seasonalReason,
        'admin_override' => $forcedIcon,
        'override_reason' => $overrideReason,
        'reason' => $activeReason,
        'updated_at' => $iconConfig['updated_at'] ?? null,
        'server_time' => $now->toIso8601String(),
      ],
      'ota_update' => [
        'force' => (bool) $isForceOta,
        'min_version' => $otaConfig['min_version'] ?? (isset($otaConfig['min_build']) && $otaConfig['min_build'] !== '' ? (string) $otaConfig['min_build'] : null),
        'max_version' => $otaConfig['max_version'] ?? (isset($otaConfig['max_build']) && $otaConfig['max_build'] !== '' ? (string) $otaConfig['max_build'] : null),
        'min_build' => isset($otaConfig['min_build']) && $otaConfig['min_build'] !== '' ? (int) $otaConfig['min_build'] : null,
        'max_build' => isset($otaConfig['max_build']) && $otaConfig['max_build'] !== '' ? (int) $otaConfig['max_build'] : null,
        'channel' => !empty($otaConfig['channel']) ? $otaConfig['channel'] : 'all',
        'message' => $otaConfig['message'] ?? null,
        'updated_at' => $otaConfig['updated_at'] ?? null,
      ],
    ]);
  }

  /**
   * Admin endpoint to update app OTA configuration.
   *
   * POST /v1.0/admin/app-ota
   * Body: { force: boolean, min_version?: string|null, max_version?: string|null, min_build?: number|null, max_build?: number|null, channel?: string|null, message?: string|null }
   */
  public function updateAppOta(Request $request)
  {
    $this->authorizeAdmin($request);

    $validated = $request->validate([
      'force' => 'required|boolean',
      'min_version' => 'nullable|string|max:50',
      'max_version' => 'nullable|string|max:50',
      'min_build' => 'nullable|integer|min:1',
      'max_build' => 'nullable|integer|min:1',
      'channel' => 'nullable|string|in:all,production,preview',
      'message' => 'nullable|string|max:255',
    ]);

    $configFile = storage_path('app/app_config.json');
    $configDir = dirname($configFile);
    if (!is_dir($configDir)) {
      @mkdir($configDir, 0755, true);
    }

    $currentConfig = file_exists($configFile)
      ? json_decode((string) file_get_contents($configFile), true)
      : [];

    if (!is_array($currentConfig)) {
      $currentConfig = [];
    }

    $previousOtaConfig = $currentConfig['ota_update'] ?? null;

    // Handle min_version / min_build sync (oldest version affected)
    $minVersion = $validated['min_version'] ?? null;
    $minBuild = isset($validated['min_build']) && $validated['min_build'] !== '' ? (int) $validated['min_build'] : null;
    if ($minBuild === null && $minVersion !== null && ctype_digit($minVersion)) {
      $minBuild = (int) $minVersion;
    }
    if ($minVersion === null && $minBuild !== null) {
      $minVersion = (string) $minBuild;
    }

    // Handle max_version / max_build sync (highest version forced to upgrade)
    $maxVersion = $validated['max_version'] ?? null;
    $maxBuild = isset($validated['max_build']) && $validated['max_build'] !== '' ? (int) $validated['max_build'] : null;
    if ($maxBuild === null && $maxVersion !== null && ctype_digit($maxVersion)) {
      $maxBuild = (int) $maxVersion;
    }
    if ($maxVersion === null && $maxBuild !== null) {
      $maxVersion = (string) $maxBuild;
    }

    $channel = !empty($validated['channel']) ? $validated['channel'] : 'all';

    $currentConfig['ota_update'] = [
      'force' => (bool) $validated['force'],
      'min_version' => $minVersion,
      'max_version' => $maxVersion,
      'min_build' => $minBuild,
      'max_build' => $maxBuild,
      'channel' => $channel,
      'message' => $validated['message'] ?? null,
      'updated_at' => Carbon::now('Asia/Ho_Chi_Minh')->toIso8601String(),
    ];

    file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Record server-side change in Audit Log diary
    $adminUser = auth('sanctum')->user() ?: $request->user();
    AuditLog::record('ADMIN_FORCE_OTA_UPDATE', $adminUser?->id, [
      'actor_id' => $adminUser?->id,
      'target_type' => 'system_config',
      'old' => $previousOtaConfig,
      'new' => $currentConfig['ota_update'],
      'status' => AuditLog::STATUS_RESOLVED,
    ]);

    return response()->json([
      'message' => 'Cập nhật cấu hình bắt buộc nâng cấp (OTA) thành công.',
      'ota_update' => $currentConfig['ota_update'],
    ]);
  }

  /**
   * Admin endpoint to update app icon configuration.
   *
   * POST /v1.0/admin/app-icon
   * Body: { icon: "TeachersDay"|"Christmas"|"default"|null, force: boolean, reason?: string }
   */
  public function updateAppIcon(Request $request)
  {
    $this->authorizeAdmin($request);

    $validated = $request->validate([
      'icon' => 'nullable|string|in:default,Dark,ClearDark,ClearLight,TintedDark,TintedLight,TeachersDay,Classic,Christmas,NewYear2026,NewYear2027,NewYear,SchoolCrest',
      'force' => 'nullable|boolean',
      'reason' => 'nullable|string|max:255',
    ]);

    $targetIcon = $validated['icon'] ?? null;
    if ($targetIcon === 'NewYear') {
      $targetIcon = now()->year >= 2027 ? 'NewYear2027' : 'NewYear2026';
    }

    $configFile = storage_path('app/app_config.json');
    $configDir = dirname($configFile);
    if (!is_dir($configDir)) {
      @mkdir($configDir, 0755, true);
    }

    $currentConfig = file_exists($configFile)
      ? json_decode((string) file_get_contents($configFile), true)
      : [];

    if (!is_array($currentConfig)) {
      $currentConfig = [];
    }

    $previousIconConfig = $currentConfig['app_icon'] ?? null;

    $currentConfig['app_icon'] = [
      'icon' => $targetIcon,
      'force' => (bool) ($validated['force'] ?? false),
      'reason' => $validated['reason'] ?? null,
      'updated_at' => Carbon::now()->toIso8601String(),
    ];

    file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Record server-side change in Audit Log diary
    $adminUser = auth('sanctum')->user() ?: $request->user();
    AuditLog::record('ADMIN_CHANGE_APP_ICON', $adminUser?->id, [
      'actor_id' => $adminUser?->id,
      'target_type' => 'system_config',
      'old' => $previousIconConfig,
      'new' => $currentConfig['app_icon'],
      'status' => AuditLog::STATUS_RESOLVED,
    ]);

    return response()->json([
      'message' => 'Cập nhật cấu hình biểu tượng ứng dụng thành công.',
      'app_icon' => $currentConfig['app_icon'],
    ]);
  }

  /**
   * Authorize admin action via Sanctum role or OTA/admin deploy token.
   */
  private function authorizeAdmin(Request $request): void
  {
    $token = $request->bearerToken() ?: $request->header('X-OTA-Token');
    $configuredToken = config('services.ota.deploy_token') ?: env('OTA_DEPLOY_TOKEN');

    if (!empty($configuredToken) && !empty($token) && hash_equals((string) $configuredToken, (string) $token)) {
      return;
    }

    $user = auth('sanctum')->user() ?: $request->user();
    if ($user && \App\Models\AuthAccount::isStaffRole($user->role)) {
      return;
    }

    abort(403, 'Forbidden. Cần tài khoản quản trị viên hoặc giáo viên.');
  }
}
