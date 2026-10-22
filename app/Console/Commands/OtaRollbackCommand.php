<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OtaRollbackCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'ota:rollback
                          {--channel=production : Target channel}
                          {--runtime-version=7.4.10 : Target runtime version}
                          {--target=embedded : Target update ID or "embedded" to roll back to binary}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Roll back active OTA updates to embedded binary or a specific update ID';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $channel = $this->option('channel');
    $runtimeVersion = $this->option('runtime-version');
    $target = $this->option('target');

    $otaDir = storage_path("app/ota/{$channel}/{$runtimeVersion}");
    $currentFile = "{$otaDir}/current.json";

    if (!file_exists($currentFile)) {
      $this->error("No active OTA updates found for channel [{$channel}], runtime [{$runtimeVersion}].");
      return 1;
    }

    $current = json_decode((string) file_get_contents($currentFile), true) ?: [];

    if ($target === 'embedded') {
      $current['rollback'] = true;
      $current['message'] = 'Rolled back to embedded binary via CLI';
      $current['updated_at'] = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
      $this->info("Successfully set rollback directive to embedded binary for channel [{$channel}].");
    } else {
      $targetDir = "{$otaDir}/{$target}";
      if (!is_dir($targetDir)) {
        $this->error("Target update directory [{$targetDir}] does not exist.");
        return 1;
      }
      $current['update_id'] = $target;
      $current['rollback'] = false;
      $current['message'] = "Rolled back to update ID {$target} via CLI";
      $current['updated_at'] = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
      $this->info("Successfully switched active update to [{$target}] for channel [{$channel}].");
    }

    file_put_contents($currentFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return 0;
  }
}
