<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\AuditLog;

class SetForceOtaCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'app:ota
                          {action? : Action to perform: status, force, reset, clear, unforce}
                          {--force : Force OTA auto-update on mobile regardless of settings}
                          {--unforce : Disable force OTA auto-update}
                          {--min-version= : Oldest version or build affected (e.g. 1020 or 6.4.0)}
                          {--max-version= : Highest version or build forced to upgrade (e.g. 1050 or 6.4.2)}
                          {--min-build= : Target minimum build number (alias for --min-version)}
                          {--max-build= : Target maximum build number (alias for --max-version)}
                          {--channel= : Target channel: all, production, or preview}
                          {--message= : Reason or message displayed for the forced upgrade}
                          {--status : Show current OTA configuration status}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Configure forced Over-The-Air (OTA) auto-updates for mobile clients regardless of user settings';

  /**
   * Execute the console command.
   */
  public function handle(): int
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

    $action = strtolower((string) $this->argument('action'));
    $wantsStatus = $this->option('status') || $action === 'status';
    $wantsUnforce = $this->option('unforce') || in_array($action, ['reset', 'clear', 'unforce'], true);
    $isForce = $this->option('force') || $action === 'force';
    $minVersion = $this->option('min-version') ?: $this->option('min-build');
    $maxVersion = $this->option('max-version') ?: $this->option('max-build');
    $channel = $this->option('channel');
    $message = $this->option('message');

    // Show status if explicitly requested or with no options/arguments given
    if ($wantsStatus || (!$action && !$this->option('force') && !$this->option('unforce') && !$minVersion && !$maxVersion && !$channel && !$message)) {
      return $this->showStatus($currentConfig);
    }

    $previousOtaConfig = $currentConfig['ota_update'] ?? null;

    // Reset / Unforce mode
    if ($wantsUnforce && !$isForce) {
      if (isset($currentConfig['ota_update'])) {
        $currentConfig['ota_update']['force'] = false;
        $currentConfig['ota_update']['updated_at'] = Carbon::now('Asia/Ho_Chi_Minh')->toIso8601String();
      }

      file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

      AuditLog::record('ADMIN_FORCE_OTA_UPDATE', null, [
        'actor_type' => 'cli_artisan',
        'target_type' => 'system_config',
        'old' => $previousOtaConfig,
        'new' => $currentConfig['ota_update'] ?? null,
        'action_detail' => 'Disabled forced OTA auto-update via CLI',
        'status' => AuditLog::STATUS_RESOLVED,
      ]);

      $this->info("✓ Disabled forced OTA auto-update mode. Mobile clients will follow regular user update preferences.");
      return $this->showStatus($currentConfig);
    }

    // Validate channel if provided
    if ($channel && !in_array($channel, ['all', 'production', 'preview'], true)) {
      $this->error("Invalid OTA channel: '{$channel}'. Allowed values: all, production, preview");
      return 1;
    }

    $minBuildInt = ($minVersion !== null && ctype_digit((string) $minVersion)) ? (int) $minVersion : null;
    $maxBuildInt = ($maxVersion !== null && ctype_digit((string) $maxVersion)) ? (int) $maxVersion : null;

    $currentConfig['ota_update'] = [
      'force' => true,
      'min_version' => $minVersion ? (string) $minVersion : null,
      'max_version' => $maxVersion ? (string) $maxVersion : null,
      'min_build' => $minBuildInt,
      'max_build' => $maxBuildInt,
      'channel' => $channel ?: 'all',
      'message' => $message ?: 'Quản trị viên yêu cầu nâng cấp bắt buộc phiên bản mới.',
      'updated_at' => Carbon::now('Asia/Ho_Chi_Minh')->toIso8601String(),
    ];

    file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    AuditLog::record('ADMIN_FORCE_OTA_UPDATE', null, [
      'actor_type' => 'cli_artisan',
      'target_type' => 'system_config',
      'old' => $previousOtaConfig,
      'new' => $currentConfig['ota_update'],
      'action_detail' => 'Enforced OTA auto-update via CLI' . ($minVersion ? " [min={$minVersion}]" : '') . ($maxVersion ? " [max={$maxVersion}]" : '') . " [channel=" . ($channel ?: 'all') . "]",
      'status' => AuditLog::STATUS_RESOLVED,
    ]);

    $this->info("✓ Forced OTA auto-update enabled successfully!");
    return $this->showStatus($currentConfig);
  }

  /**
   * Display status summary and helper guide.
   */
  private function showStatus(array $currentConfig): int
  {
    $ota = $currentConfig['ota_update'] ?? [];
    $isForce = !empty($ota['force']);
    $minVersion = $ota['min_version'] ?? ($ota['min_build'] ?? 'None (Oldest builds affected)');
    $maxVersion = $ota['max_version'] ?? ($ota['max_build'] ?? 'None (All builds up to latest)');
    $channel = !empty($ota['channel'])
      ? ($ota['channel'] === 'all' ? 'All branches (Tất cả các nhánh)' : $ota['channel'])
      : 'All branches (Tất cả các nhánh)';
    $message = $ota['message'] ?? 'N/A';
    $updatedAt = $ota['updated_at'] ?? 'Never configured';

    $this->line("");
    $this->comment("=== CBH Youth Online - Remote Mobile OTA Force Configuration ===");
    $this->table(
      ['Setting', 'Current State', 'Notes'],
      [
        ['Force Auto-Update', $isForce ? 'ACTIVE (TRUE)' : 'INACTIVE (FALSE)', $isForce ? 'Mobile app MUST auto-update regardless of settings' : 'App obeys user in-app toggle'],
        ['Minimum Version (Oldest affected)', $minVersion, 'Clients older than this version/build are NOT forced'],
        ['Maximum Version (Highest forced)', $maxVersion, 'Clients newer than this version/build are NOT forced'],
        ['Target Channel', $channel, 'All branches or specific branch (production/preview)'],
        ['Reason / Message', $message, 'Admin explanation or prompt text'],
        ['Last Updated', $updatedAt, 'Recorded in Vietnam time (Asia/Ho_Chi_Minh)'],
      ]
    );

    $this->line("");
    $this->comment("Available Artisan Commands for Admins:");
    $this->line("  php artisan app:ota --status                                                  # View current OTA force status");
    $this->line("  php artisan app:ota force --min-version=1020 --max-version=1050               # Force upgrade range [1020, 1050]");
    $this->line("  php artisan app:ota force --channel=all                                       # Force upgrade across all branches to each branch latest");
    $this->line("  php artisan app:ota force --channel=preview                                   # Force update only on preview channel");
    $this->line("  php artisan app:ota reset                                                     # Disable forced OTA mode (return to normal)");
    $this->line("");

    return 0;
  }
}
