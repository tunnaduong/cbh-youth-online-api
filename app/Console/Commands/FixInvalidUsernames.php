<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\UserProfile;
use App\Services\MentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixInvalidUsernames extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'users:fix-invalid-usernames {--dry-run : Show what would change without writing anything}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Strip unicode/space/other banned characters from existing usernames so they match the [a-zA-Z0-9_.-] rule enforced on registration and profile updates';

  private const ALLOWED_PATTERN = '/[^a-zA-Z0-9_.\-]/u';

  public function handle()
  {
    $dryRun = (bool) $this->option('dry-run');

    $this->info($dryRun ? '🔍 Dry run - no changes will be written.' : '🔧 Fixing invalid usernames...');

    $accounts = AuthAccount::whereRaw("username REGEXP '[^a-zA-Z0-9_.-]'")->get();

    $this->info("Found {$accounts->count()} account(s) with invalid usernames.");

    $fixed = 0;
    $skipped = 0;

    foreach ($accounts as $account) {
      $oldUsername = $account->username;
      $sanitized = preg_replace(self::ALLOWED_PATTERN, '', $oldUsername);
      $sanitized = trim($sanitized, '.-_');

      if ($sanitized === '') {
        $sanitized = 'user' . $account->id;
      }

      if (strlen($sanitized) < 3) {
        $sanitized = str_pad($sanitized, 3, '0');
      }

      $sanitized = substr($sanitized, 0, 21);

      $newUsername = $this->makeUnique($sanitized, $account->id);

      if ($newUsername === $oldUsername) {
        $skipped++;
        continue;
      }

      $this->line("  {$oldUsername} -> {$newUsername}" . ($dryRun ? ' (dry run)' : ''));

      if (!$dryRun) {
        DB::transaction(function () use ($account, $oldUsername, $newUsername) {
          $account->username = $newUsername;
          $account->save();

          UserProfile::where('auth_account_id', $account->id)
            ->update(['profile_username' => $newUsername]);

          MentionService::renameMentions($oldUsername, $newUsername);
        });
      }

      $fixed++;
    }

    $label = $dryRun ? 'would be fixed' : 'fixed';
    $this->info("✅ {$fixed} username(s) {$label}, {$skipped} left unchanged (sanitized to empty/duplicate of itself).");

    return 0;
  }

  private function makeUnique(string $base, int $excludeId): string
  {
    $candidate = $base;
    $suffix = 1;

    while (
      AuthAccount::where('username', $candidate)
        ->where('id', '!=', $excludeId)
        ->exists()
    ) {
      $candidate = substr($base, 0, 21 - strlen((string) $suffix) - 1) . '_' . $suffix;
      $suffix++;
    }

    return $candidate;
  }
}
