<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OtaCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'ota:status
                          {--channel=production : Target channel (default: production)}
                          {--runtime-version=7.4.10 : Native runtime version (default: 7.4.10)}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Display the current Over-The-Air (OTA) update status for a channel and runtime version';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $channel = $this->option('channel');
    $runtimeVersion = $this->option('runtime-version');

    $otaDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentFile = "{$otaDir}/current.json";

    $this->info("OTA Status for channel [{$channel}], runtime-version [{$runtimeVersion}]:");

    if (!file_exists($currentFile)) {
      $this->warn("No active OTA updates published yet.");
      return 0;
    }

    $current = json_decode((string) file_get_contents($currentFile), true);
    if (!is_array($current)) {
      $this->error("Invalid current.json found.");
      return 1;
    }

    $rows = [
      ['Update ID', $current['update_id'] ?? 'N/A'],
      ['Message', $current['message'] ?? 'N/A'],
      ['Created At', $current['created_at'] ?? 'N/A'],
      ['Rollback Mode', !empty($current['rollback']) ? 'YES (Roll back to embedded)' : 'NO (Active)'],
      ['Platforms', implode(', ', $current['platforms'] ?? [])],
    ];

    $this->table(['Key', 'Value'], $rows);

    return 0;
  }
}
