<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Makes sure the game proxy is running: checks /healthz and, when it does not
 * answer, starts it detached. Scheduled every minute (routes: Console/Kernel),
 * so the server starts itself and comes back after a crash or reboot with no
 * Supervisor needed. A lock file stops two runs starting it twice.
 */
class GameProxyEnsure extends Command
{
  protected $signature = 'game-proxy:ensure';

  protected $description = 'Start the game proxy if it is not running';

  public function handle(): int
  {
    if (!config('gameproxy.enabled') || !config('gameproxy.autostart')) {
      return self::SUCCESS;
    }

    if (self::healthy()) {
      return self::SUCCESS;
    }

    $binary = (string) config('gameproxy.binary');
    if (!is_file($binary) || !is_executable($binary)) {
      $this->error("Game proxy binary not found or not executable: {$binary}");
      return self::FAILURE;
    }

    $lock = fopen(storage_path('framework/game-proxy.lock'), 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
      return self::SUCCESS; // another run is already starting it
    }

    try {
      if (self::healthy()) {
        return self::SUCCESS;
      }

      $log = storage_path('logs/game-proxy.log');
      // nohup + setsid + & : the server outlives this PHP process.
      $command = sprintf(
        'nohup setsid %s >> %s 2>&1 &',
        escapeshellarg($binary),
        escapeshellarg($log)
      );
      Process::fromShellCommandline($command, base_path(), GameProxyStart::environment())
        ->disableOutput()
        ->run();

      // Give it a moment to bind its port, then confirm.
      for ($i = 0; $i < 10; $i++) {
        usleep(300000);
        if (self::healthy()) {
          $this->info('Game proxy started.');
          return self::SUCCESS;
        }
      }

      $this->error('Game proxy did not become healthy - see storage/logs/game-proxy.log');
      return self::FAILURE;
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  public static function healthy(): bool
  {
    try {
      return Http::timeout(1)->get('http://' . config('gameproxy.listen') . '/healthz')->successful();
    } catch (\Throwable $e) {
      return false;
    }
  }
}
