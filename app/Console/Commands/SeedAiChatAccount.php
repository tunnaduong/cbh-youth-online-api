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
 * Creates (or updates) the "Yoyo AI" chat persona: a real AuthAccount so all
 * existing avatar/sender/presence-channel formatting works unchanged, but
 * flagged is_ai so it's excluded from mention/search/friend suggestions.
 */
class SeedAiChatAccount extends Command
{
  protected $signature = 'ai:seed-account
      {--username=yoyo.ai : Reserved username for the AI account}
      {--name=Yoyo AI : Display name (profile_name) for the AI account}
      {--avatar-url=https://www.chuyenbienhoa.com/images/cyo_ai.png : Avatar image to download}
      {--force-avatar : Re-download and replace the avatar even if one is already set (use this to change the avatar later)}';

  protected $description = 'Create or update the Yoyo AI chat account used by the Chat with AI feature. Safe to re-run any time you want to change the name (--name) or avatar (--avatar-url --force-avatar).';

  public function handle(): int
  {
    $username = $this->option('username');
    $name = $this->option('name');
    $avatarUrl = $this->option('avatar-url');
    $forceAvatar = (bool) $this->option('force-avatar');

    $account = AuthAccount::where('username', $username)->first();

    if (!$account) {
      $account = AuthAccount::create([
        'username' => $username,
        'email' => $username . '@yoyo.internal',
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
        'profile_name' => $name,
        'verified' => true,
      ]);
    } elseif ($profile->profile_name !== $name) {
      $profile->update(['profile_name' => $name]);
      $this->info("Updated display name to \"{$name}\".");
    }

    $this->downloadAvatar($account, $profile, $avatarUrl, $forceAvatar);

    $this->info('Done.');

    return self::SUCCESS;
  }

  private function downloadAvatar(AuthAccount $account, UserProfile $profile, string $avatarUrl, bool $force): void
  {
    $existingContent = $profile->profile_picture ? UserContent::find($profile->profile_picture) : null;

    if ($existingContent && !$force) {
      $this->info('Avatar already set, skipping download (pass --force-avatar to replace it).');
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
      $fileName = 'yoyo_ai_avatar.' . $extension;
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

      // Clean up the previous avatar file/row now that the new one is live,
      // so replacing it repeatedly doesn't leak storage.
      if ($existingContent) {
        Storage::disk('public')->delete($existingContent->file_path);
        $existingContent->delete();
      }

      $this->info('Avatar downloaded and set.');
    } catch (\Throwable $e) {
      $this->warn('Error downloading avatar: ' . $e->getMessage());
    }
  }
}
