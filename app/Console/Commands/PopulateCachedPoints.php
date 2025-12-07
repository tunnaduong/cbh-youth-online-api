<?php

namespace App\Console\Commands;

use App\Services\PointsService;
use Illuminate\Console\Command;
use App\Models\AuthAccount;

class PopulateCachedPoints extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'points:populate {--force : Force update even if cached_points already exist}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Populate cached_points for all users based on their current activity';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $this->info('Starting to populate cached points...');

    $force = $this->option('force');

    // Get all users except admins
    $users = AuthAccount::where('role', '!=', 'admin')->get();

    if (!$force && $users->where('cached_points', '>', 0)->count() > 0) {
      $this->warn('Some users already have cached points. Use --force to update them.');
      return 0;
    }

    $bar = $this->output->createProgressBar($users->count());
    $bar->start();

    $successCount = 0;
    $errorCount = 0;

    foreach ($users as $user) {
      try {
        $points = PointsService::calculatePoints($user->id);
        $user->update(['points' => $points]);
        $successCount++;
      } catch (\Exception $e) {
        $this->error("Failed to update points for user {$user->username}: " . $e->getMessage());
        $errorCount++;
      }

      $bar->advance();
    }

    $bar->finish();
    $this->newLine();

    $this->info("Completed! Success: {$successCount}, Errors: {$errorCount}");

    return 0;
  }
}
