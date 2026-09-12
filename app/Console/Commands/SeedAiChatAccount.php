<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\UserContent;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates (or updates) the "CYO AI" chat persona: a real AuthAccount so all
 * existing avatar/sender/presence-channel formatting works unchanged, but
 * flagged is_ai so it's excluded from mention/search/friend suggestions.
 */
class SeedAiChatAccount extends Command
{
  protected $signature = 'ai:seed-account
      {--username=cyo.ai : Reserved username for the AI account}
      {--avatar-url=https://www.chuyenbienhoa.com/images/cyo_ai.png : Avatar image to download}';

  protected $description = 'Create or update the CYO AI chat account used by the Chat with AI feature';

  public function handle(): int
  {
    $username = $this->option('username');
    $avatarUrl = $this->option('avatar-url');

    $account = AuthAccount::where('username', $username)->first();

    if (!$account) {
      $account = AuthAccount::create([
        'username' => $username,
        'email' => $username . '@cyo.internal',
        'password' => Hash::make(Str::random(40)),
        'role' => 'user',
        'is_ai' => true,
        'email_verified_at' => now(),
      ]);
      $this->info("Created AuthAccount #{$account->id} ({$username}).");
    } elseif (!$account->is_ai) {
      $account->update(['is_ai' => true]);
      $this->info("Flagged existing account #{$account->id} ({$username}) as is_ai.");
    } else {
      $this->info("AuthAccount #{$account->id} ({$username}) already exists.");
    }

    $profile = $account->profile;
    if (!$profile) {
      $profile = UserProfile::create([
        'auth_account_id' => $account->id,
        'profile_name' => 'CYO AI',
        'verified' => true,
      ]);
    } elseif ($profile->profile_name !== 'CYO AI') {
      $profile->update(['profile_name' => 'CYO AI']);
    }

    $this->downloadAvatar($account, $profile, $avatarUrl);

    $this->info('Done.');

    return self::SUCCESS;
  }

  private function downloadAvatar(AuthAccount $account, UserProfile $profile, string $avatarUrl): void
  {
    if ($profile->profile_picture && UserContent::find($profile->profile_picture)) {
      $this->info('Avatar already set, skipping download.');
      return;
    }

    try {
      $response = Http::timeout(30)->get($avatarUrl);
      if (!$response->successful()) {
        $this->warn("Failed to download avatar: HTTP {$response->status()}");
        return;
      }

      $contentType = $response->header('Content-Type') ?: 'image/png';
      $extension = str_contains($contentType, 'jpeg') ? 'jpg' : 'png';
      $fileName = 'cyo_ai_avatar.' . $extension;
      $path = 'avatars/' . Str::uuid() . '.' . $extension;

      Storage::disk('public')->put($path, $response->body());

      $userContent = UserContent::create([
        'user_id' => $account->id,
        'file_name' => $fileName,
        'file_path' => $path,
        'file_type' => $contentType,
        'file_size' => strlen($response->body()),
      ]);

      $profile->update(['profile_picture' => $userContent->id]);

      $this->info('Avatar downloaded and set.');
    } catch (\Throwable $e) {
      $this->warn('Error downloading avatar: ' . $e->getMessage());
    }
  }
}
