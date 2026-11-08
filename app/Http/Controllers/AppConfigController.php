<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
    // New Year season: Dec 28 to Jan 5
    elseif (($month === 12 && $day >= 28) || ($month === 1 && $day <= 5)) {
      $seasonalIcon = 'NewYear2026';
      $seasonalReason = "Chào đón Năm mới 2026";
    }

    // Admin override from storage/app/app_config.json if set
    $iconConfig = $storedConfig['app_icon'] ?? [];
    $forcedIcon = !empty($iconConfig['icon']) ? $iconConfig['icon'] : null;
    $isForce = !empty($iconConfig['force']);
    $overrideReason = $iconConfig['reason'] ?? null;

    // Active target icon: if admin set a specific icon, that takes precedence; otherwise seasonal
    $activeIcon = $forcedIcon ?: $seasonalIcon;
    $activeReason = $forcedIcon ? $overrideReason : $seasonalReason;

    return response()->json([
      'app_icon' => [
        'target_icon' => $activeIcon,
        'force' => (bool) $isForce,
        'seasonal_icon' => $seasonalIcon,
        'reason' => $activeReason,
        'server_time' => $now->toIso8601String(),
      ],
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
      'icon' => 'nullable|string|in:default,Dark,ClearDark,ClearLight,TintedDark,TintedLight,TeachersDay,Classic,Christmas,NewYear2026,SchoolCrest',
      'force' => 'nullable|boolean',
      'reason' => 'nullable|string|max:255',
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

    $currentConfig['app_icon'] = [
      'icon' => $validated['icon'] ?? null,
      'force' => (bool) ($validated['force'] ?? false),
      'reason' => $validated['reason'] ?? null,
      'updated_at' => Carbon::now()->toIso8601String(),
    ];

    file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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

    $user = auth('sanctum')->user();
    if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
      return;
    }

    abort(401, 'Unauthorized. Cần quyền quản trị viên.');
  }
}
