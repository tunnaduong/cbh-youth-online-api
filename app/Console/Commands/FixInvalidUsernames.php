<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use Illuminate\Console\Command;

class FixInvalidUsernames extends Command
{
    protected $signature = 'users:fix-invalid-usernames
        {--dry-run : List offending accounts without changing data}';

    protected $description = 'Sanitize usernames that violate the ^[a-zA-Z0-9_.-]{3,20}$ rule (and the reserved word "all")';

    private const PATTERN = '/^[a-zA-Z0-9_.-]{3,20}$/';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $offenders = [];

        AuthAccount::query()->orderBy('id')->chunkById(200, function ($chunk) use (&$offenders) {
            foreach ($chunk as $account) {
                if (!$this->isValid($account->username)) {
                    $offenders[] = $account;
                }
            }
        });

        if (empty($offenders)) {
            $this->info('No accounts with an invalid username were found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['id', 'current username', 'would become'],
                collect($offenders)->map(fn (AuthAccount $a) => [
                    $a->id,
                    $a->username,
                    $this->sanitize($a),
                ])
            );
            $this->info(count($offenders) . ' account(s) would be updated.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($offenders as $account) {
            $newUsername = $this->sanitize($account);
            $this->line("#{$account->id}: \"{$account->username}\" -> \"{$newUsername}\"");
            $account->username = $newUsername;
            $account->save();
            $updated++;
        }

        $this->info("Fixed {$updated} account(s).");

        return self::SUCCESS;
    }

    private function isValid(?string $username): bool
    {
        return $username !== null
            && preg_match(self::PATTERN, $username) === 1
            && strtolower($username) !== 'all';
    }

    private function sanitize(AuthAccount $account): string
    {
        // Strip everything except letters, digits, underscore, dot and dash.
        $clean = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $account->username);

        if (strtolower($clean) === 'all') {
            $clean .= '_user';
        }

        if (strlen($clean) < 3) {
            $clean = str_pad($clean, 3, '0');
        }

        $clean = substr($clean, 0, 20);

        // Resolve collisions (including with other accounts about to be renamed
        // in this same run) by appending the account id.
        $base = $clean;
        while (
            AuthAccount::where('username', $clean)->where('id', '!=', $account->id)->exists()
        ) {
            $suffix = '_' . $account->id;
            $clean = substr($base, 0, 20 - strlen($suffix)) . $suffix;
        }

        return $clean;
    }
}
