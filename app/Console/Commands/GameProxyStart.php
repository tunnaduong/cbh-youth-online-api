<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Runs the game proxy in the foreground (for Supervisor / systemd, like
 * reverb:start). Everyone else can leave it to game-proxy:ensure.
 */
class GameProxyStart extends Command
{
  protected $signature = 'game-proxy:start';

  protected $description = 'Run the game proxy server (Go) in the foreground';

  public function handle(): int
  {
    $binary = (string) config('gameproxy.binary');

    if (!config('gameproxy.enabled')) {
      $this->warn('GAME_PROXY_ENABLED is off - not starting.');
      return self::SUCCESS;
    }
    if (!is_file($binary) || !is_executable($binary)) {
      $this->error("Game proxy binary not found or not executable: {$binary} (see game-proxy/README.md).");
      return self::FAILURE;
    }

    $process = new Process([$binary], base_path(), self::environment());
    $process->setTimeout(null);
    $process->setIdleTimeout(null);

    $this->info('Game proxy listening on ' . config('gameproxy.listen'));
    $process->run(function ($type, $buffer) {
      $this->output->write($buffer);
    });

    return $process->getExitCode() ?? self::FAILURE;
  }

  /** What the Go server reads. Laravel stays the single place the config lives. */
  public static function environment(): array
  {
    return [
      'GP_LISTEN' => (string) config('gameproxy.listen'),
      'GP_SECRET' => (string) config('gameproxy.secret'),
      'GP_API_URL' => (string) config('gameproxy.api_url'),
      'GP_PARENT_ORIGINS' => implode(',', config('gameproxy.parent_origins')),
      'GP_BLOCKLIST_FILE' => (string) config('gameproxy.blocklist_file'),
      'GP_MAX_SAVE_BYTES' => (string) config('gameproxy.max_save_bytes'),
    ];
  }
}
