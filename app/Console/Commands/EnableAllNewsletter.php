<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\NotificationSettings;
use Illuminate\Console\Command;

class EnableAllNewsletter extends Command
{
    protected $signature = 'newsletter:enable-all
        {--dry-run : Show how many accounts would be enabled without changing data}';

    protected $description = 'Enable marketing email newsletters for all accounts with an email address';

    public function handle(): int
    {
        $accounts = AuthAccount::query()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($this->option('dry-run')) {
            $this->info("{$accounts->count()} account(s) would be enabled.");

            return self::SUCCESS;
        }

        $updated = 0;
        $accounts->chunkById(100, function ($chunk) use (&$updated) {
            foreach ($chunk as $account) {
                NotificationSettings::updateOrCreate(
                    ['user_id' => $account->id],
                    ['notify_email_marketing' => true],
                );
                $updated++;
            }
        });

        $this->info("Enabled newsletter for {$updated} account(s).");

        return self::SUCCESS;
    }
}