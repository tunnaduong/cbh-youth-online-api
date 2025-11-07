<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
  /**
   * Redirect the user to the provider's authentication page.
   *
   * @param string $provider
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function redirectToProvider(string $provider)
  {
    return Socialite::driver($provider)->redirect();
  }

  /**
   * Obtain the user information from the provider.
   *
   * @param string $provider
   * @return \Illuminate\Http\RedirectResponse
   */
  public function handleProviderCallback(string $provider)
  {
    try {
      $socialUser = Socialite::driver($provider)->user();
    } catch (\Exception $e) {
      return redirect()->route('login')->with('error', 'Đăng nhập thất bại. Vui lòng thử lại.');
    }

    // Find or create the user
    $user = AuthAccount::where('provider', $provider)
      ->where('provider_id', $socialUser->getId())
      ->first();

    if (!$user) {
      // If user does not exist, check by email
      $user = AuthAccount::where('email', $socialUser->getEmail())->first();

      if ($user) {
        // Update existing user with provider details
        $user->update([
          'provider' => $provider,
          'provider_id' => $socialUser->getId(),
          'provider_token' => $socialUser->token,
        ]);
      } else {
        // Create a new user
        $user = AuthAccount::create([
          'username' => $this->generateUniqueUsername($socialUser->getName()),
          'email' => $socialUser->getEmail(),
          'password' => null, // No password for social logins
          'provider' => $provider,
          'provider_id' => $socialUser->getId(),
          'provider_token' => $socialUser->token,
          'email_verified_at' => now(), // Assume email is verified by provider
        ]);

        // Create a user profile
        UserProfile::create([
          'auth_account_id' => $user->id,
          'profile_name' => $socialUser->getName(),
        ]);

        // Create welcome notification for new social user
        try {
          NotificationService::createWelcomeNotification($user->id);
        } catch (\Exception $e) {
          // Log error but don't fail registration
          \Log::warning('Failed to create welcome notification for social user', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
          ]);
        }
      }
    } else {
      // Update the token for existing social user
      $user->update([
        'provider_token' => $socialUser->token,
      ]);
    }

    // Log in the user
    Auth::login($user);

    // Redirect to the dashboard
    return redirect()->route('home');
  }

  /**
   * Generate a unique username from the given name.
   *
   * @param string $name
   * @return string
   */
  private function generateUniqueUsername(string $name): string
  {
    $username = Str::slug($name);
    $originalUsername = $username;
    $counter = 1;

    while (AuthAccount::where('username', $username)->exists()) {
      $username = $originalUsername . $counter;
      $counter++;
    }

    return $username;
  }
}
