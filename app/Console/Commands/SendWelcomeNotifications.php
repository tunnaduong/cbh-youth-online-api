<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendWelcomeNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-welcome {--skip-existing : Skip users who already have a welcome notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send welcome notifications to all users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to send welcome notifications...');

        $skipExisting = $this->option('skip-existing');

        // Get all users
        $users = AuthAccount::all();

        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return 0;
        }

        $this->info("Found {$users->count()} users.");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            try {
                // Check if user already has a welcome notification
                if ($skipExisting) {
                    $existingNotification = \App\Models\Notification::where('user_id', $user->id)
                        ->where('type', 'system_message')
                        ->get()
                        ->first(function ($notification) {
                            $data = $notification->data ?? [];
                            return isset($data['message']) && $data['message'] === 'Chào mừng bạn đến với diễn đàn!';
                        });

                    if ($existingNotification) {
                        $skippedCount++;
                        $bar->advance();
                        continue;
                    }
                }

                // Create welcome notification
                $notification = NotificationService::createWelcomeNotification($user->id);

                if ($notification) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $this->warn("\nFailed to create notification for user {$user->username} (ID: {$user->id})");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("\nError creating notification for user {$user->username} (ID: {$user->id}): " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Completed!");
        $this->info("  Success: {$successCount}");
        if ($skippedCount > 0) {
            $this->info("  Skipped: {$skippedCount}");
        }
        if ($errorCount > 0) {
            $this->error("  Errors: {$errorCount}");
        }

        return 0;
    }
}

