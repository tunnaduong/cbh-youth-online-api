<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\ExpoPushToken;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExpoNotification extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'expo:send {user : User ID or username} {title : Notification title} {body : Notification body} {--data= : Additional data as JSON string} {--all : Send to all users with active Expo tokens}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Send Expo push notification to a user or all users';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $sendToAll = $this->option('all');

    if ($sendToAll) {
      return $this->sendToAllUsers();
    }

    $userIdentifier = $this->argument('user');
    $title = $this->argument('title');
    $body = $this->argument('body');
    $dataJson = $this->option('data');

    // Parse additional data if provided
    $data = [];
    if ($dataJson) {
      $decoded = json_decode($dataJson, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $data = $decoded;
      } else {
        $this->error('Invalid JSON in --data option: ' . json_last_error_msg());
        return 1;
      }
    }

    // Find user by ID or username
    $user = is_numeric($userIdentifier)
      ? AuthAccount::find($userIdentifier)
      : AuthAccount::where('username', $userIdentifier)->first();

    if (!$user) {
      $this->error("User not found: {$userIdentifier}");
      return 1;
    }

    $this->info("Sending Expo push notification to user: {$user->username} (ID: {$user->id})");
    $this->info("Title: {$title}");
    $this->info("Body: {$body}");

    if (!empty($data)) {
      $this->info("Data: " . json_encode($data, JSON_PRETTY_PRINT));
    }

    // Check if user has active Expo tokens
    $tokenCount = ExpoPushToken::where('user_id', $user->id)
      ->where('is_active', true)
      ->count();

    if ($tokenCount === 0) {
      $this->warn("User has no active Expo push tokens. Notification will not be sent.");
      return 0;
    }

    $this->info("Found {$tokenCount} active Expo push token(s) for this user.");

    try {
      $sentCount = PushNotificationService::sendExpoPushToUserWithPayload(
        $user->id,
        $title,
        $body,
        $data
      );

      if ($sentCount > 0) {
        $this->info("✓ Successfully sent notification to {$sentCount} device(s).");
        return 0;
      } else {
        $this->warn("No notifications were sent (all tokens may be invalid).");
        return 0;
      }
    } catch (\Exception $e) {
      $this->error("Error sending notification: " . $e->getMessage());
      Log::error("Error sending Expo notification via command", [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return 1;
    }
  }

  /**
   * Send notification to all users with active Expo tokens.
   *
   * @return int
   */
  private function sendToAllUsers(): int
  {
    $title = $this->argument('title');
    $body = $this->argument('body');
    $dataJson = $this->option('data');

    // Parse additional data if provided
    $data = [];
    if ($dataJson) {
      $decoded = json_decode($dataJson, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $data = $decoded;
      } else {
        $this->error('Invalid JSON in --data option: ' . json_last_error_msg());
        return 1;
      }
    }

    $this->info('Sending Expo push notification to all users with active tokens...');
    $this->info("Title: {$title}");
    $this->info("Body: {$body}");

    if (!empty($data)) {
      $this->info("Data: " . json_encode($data, JSON_PRETTY_PRINT));
    }

    // Get all unique user IDs with active Expo tokens
    $userIds = ExpoPushToken::where('is_active', true)
      ->distinct()
      ->pluck('user_id')
      ->toArray();

    if (empty($userIds)) {
      $this->warn('No users with active Expo push tokens found.');
      return 0;
    }

    $this->info("Found " . count($userIds) . " user(s) with active Expo push tokens.");

    if (!$this->confirm('Do you want to proceed with sending notifications to all these users?')) {
      $this->info('Operation cancelled.');
      return 0;
    }

    $bar = $this->output->createProgressBar(count($userIds));
    $bar->start();

    $successCount = 0;
    $errorCount = 0;
    $totalSentCount = 0;

    foreach ($userIds as $userId) {
      try {
        $sentCount = PushNotificationService::sendExpoPushToUserWithPayload(
          $userId,
          $title,
          $body,
          $data
        );

        if ($sentCount > 0) {
          $successCount++;
          $totalSentCount += $sentCount;
        }
      } catch (\Exception $e) {
        $errorCount++;
        Log::error("Error sending Expo notification to user via command", [
          'user_id' => $userId,
          'error' => $e->getMessage(),
        ]);
      }

      $bar->advance();
    }

    $bar->finish();
    $this->newLine();

    $this->info("Completed!");
    $this->info("  Users processed: " . count($userIds));
    $this->info("  Users with successful sends: {$successCount}");
    $this->info("  Total devices notified: {$totalSentCount}");
    if ($errorCount > 0) {
      $this->error("  Errors: {$errorCount}");
    }

    return 0;
  }
}
