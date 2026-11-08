<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\AuditLog;

class SetAppIconCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'app:icon
                          {icon? : Icon preset name (TeachersDay, Christmas, NewYear2026, Dark, ClearDark, ClearLight, TintedDark, TintedLight, Classic, SchoolCrest, default, reset)}
                          {--force : Force icon switch for ALL users regardless of their manual preset selection}
                          {--reason= : Reason or event description for the icon directive}
                          {--status : Show the current active icon configuration and seasonal calendar}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Set, force, or inspect the app launcher icon directive for mobile clients';

  /**
   * Allowed icon presets supported by the mobile native launcher aliases.
   */
  private const VALID_ICONS = [
    'default',
    'TeachersDay',
    'Christmas',
    'NewYear2026',
    'Dark',
    'ClearDark',
    'ClearLight',
    'TintedDark',
    'TintedLight',
    'Classic',
    'SchoolCrest',
  ];

  /**
   * Execute the console command.
   */
  public function handle()
  {
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

    // Status inspection mode or no argument provided
    if ($this->option('status') || $this->argument('icon') === null) {
      return $this->showStatus($currentConfig);
    }

    $iconArg = $this->argument('icon');
    $isForce = (bool) $this->option('force');
    $reason = $this->option('reason');

    $previousIconConfig = $currentConfig['app_icon'] ?? null;

    // Handle "reset" command keyword
    if (strtolower($iconArg) === 'reset' || strtolower($iconArg) === 'clear') {
      unset($currentConfig['app_icon']);
      file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

      AuditLog::record('ADMIN_CHANGE_APP_ICON', null, [
        'actor_type' => 'cli_artisan',
        'target_type' => 'system_config',
        'old' => $previousIconConfig,
        'new' => null,
        'action_detail' => 'Reset to seasonal auto calendar via CLI',
        'status' => AuditLog::STATUS_RESOLVED,
      ]);

      $this->info("✓ Reset remote icon override. App will automatically follow the seasonal calendar.");
      return $this->showStatus($currentConfig);
    }

    // Match case-insensitively to valid icons
    $matchedIcon = null;
    foreach (self::VALID_ICONS as $valid) {
      if (strcasecmp($valid, $iconArg) === 0) {
        $matchedIcon = $valid;
        break;
      }
    }

    if (!$matchedIcon) {
      $this->error("Invalid icon preset: '{$iconArg}'");
      $this->line("");
      $this->warn("Supported icon presets:");
      foreach (self::VALID_ICONS as $valid) {
        $this->line("  - {$valid}");
      }
      $this->line("");
      $this->comment("Examples:");
      $this->line("  php artisan app:icon TeachersDay --reason=\"Tri ân ngày Nhà giáo 20/11\"");
      $this->line("  php artisan app:icon Christmas --force --reason=\"Mừng giáng sinh\"");
      $this->line("  php artisan app:icon reset");
      return 1;
    }

    $currentConfig['app_icon'] = [
      'icon' => $matchedIcon,
      'force' => $isForce,
      'reason' => $reason ?: ($isForce ? "Chỉ thị bắt buộc từ quản trị viên" : "Gợi ý biểu tượng sự kiện"),
      'updated_at' => Carbon::now('Asia/Ho_Chi_Minh')->toIso8601String(),
    ];

    file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    AuditLog::record('ADMIN_CHANGE_APP_ICON', null, [
      'actor_type' => 'cli_artisan',
      'target_type' => 'system_config',
      'old' => $previousIconConfig,
      'new' => $currentConfig['app_icon'],
      'action_detail' => "CLI override icon to {$matchedIcon} (force=" . ($isForce ? 'true' : 'false') . ")",
      'status' => AuditLog::STATUS_RESOLVED,
    ]);

    $this->info("✓ App icon directive updated successfully!");
    $this->table(
      ['Setting', 'Value'],
      [
        ['Target Icon', $matchedIcon],
        ['Force All Users', $isForce ? 'YES (Applies to all users)' : 'NO (Only users on default icon)'],
        ['Reason', $currentConfig['app_icon']['reason']],
        ['Updated At', $currentConfig['app_icon']['updated_at']],
      ]
    );

    return 0;
  }

  /**
   * Display status summary and helper guide.
   */
  private function showStatus(array $currentConfig): int
  {
    $now = Carbon::now('Asia/Ho_Chi_Minh');
    $month = (int) $now->month;
    $day = (int) $now->day;

    $seasonalIcon = 'default';
    $seasonalReason = 'Default CYO branding';

    if ($month === 11 && $day >= 14 && $day <= 21) {
      $seasonalIcon = 'TeachersDay';
      $seasonalReason = "Kỷ niệm Ngày Nhà giáo Việt Nam 20/11";
    } elseif ($month === 12 && $day >= 18 && $day <= 27) {
      $seasonalIcon = 'Christmas';
      $seasonalReason = "Mừng mùa lễ Giáng Sinh";
    } elseif (($month === 12 && $day >= 28) || ($month === 1 && $day <= 5)) {
      $seasonalIcon = 'NewYear2026';
      $seasonalReason = "Chào đón Năm mới 2026";
    }

    $override = $currentConfig['app_icon'] ?? null;
    $effectiveIcon = !empty($override['icon']) ? $override['icon'] : $seasonalIcon;

    $this->info("=== CBH Youth Online — App Icon Status ===");
    $this->line("Server Time (VN): " . $now->format('d/m/Y H:i:s'));
    $this->line("");

    $rows = [
      ['Automated Seasonal Icon', "{$seasonalIcon} ({$seasonalReason})"],
      ['Remote Override Set', !empty($override['icon']) ? $override['icon'] : '(None — following calendar)'],
      ['Force Mode', !empty($override['force']) ? 'ACTIVE (Overrides all user choices)' : 'INACTIVE (Default users only)'],
      ['Effective Active Icon', $effectiveIcon],
      ['Override Reason', $override['reason'] ?? 'N/A'],
      ['Last Updated', $override['updated_at'] ?? 'N/A'],
    ];

    $this->table(['Parameter', 'Current State'], $rows);

    $this->line("");
    $this->comment("Usage Help:");
    $this->line("  php artisan app:icon <icon> [--force] [--reason=\"...\"]");
    $this->line("  php artisan app:icon reset                  # Remove override, revert to calendar");
    $this->line("  php artisan app:icon --status               # View this status screen");
    $this->line("");
    $this->line("Available icons: " . implode(', ', self::VALID_ICONS));

    return 0;
  }
}
