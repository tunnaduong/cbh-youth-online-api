<?php

namespace App\Http\Requests\Auth;

use Illuminate\Support\Str;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Handles the form request for user authentication.
 *
 * This class includes validation rules, custom credential handling to allow
 * login with either email or username, and rate limiting to prevent brute-force attacks.
 */
class LoginRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
   */
  public function rules(): array
  {
    return [
      'email' => ['required', 'string'],
      'password' => ['required', 'string'],
    ];
  }

  /**
   * Get the authentication credentials from the request.
   * This method allows for logging in with either a username or an email address.
   *
   * @return array
   */
  protected function credentials(): array
  {
    // The form field is named 'email' but can accept a username.
    $login = $this->input('email');

    $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    return [
      $field => $login,
      'password' => $this->input('password'),
    ];
  }


  /**
   * Attempt to authenticate the request's credentials.
   *
   * @return void
   * @throws \Illuminate\Validation\ValidationException
   */
  public function authenticate(): void
  {
    $this->ensureIsNotRateLimited();

    $credentials = $this->credentials();
    $loginField  = array_key_first($credentials); // email or username
    $loginValue  = $credentials[$loginField];

    // Find user by email or username
    $user = \App\Models\User::where($loginField, $loginValue)->first();

    if (!$user) {
      RateLimiter::hit($this->throttleKey());
      throw ValidationException::withMessages([
        'email' => 'Tài khoản không tồn tại.',
      ]);
    }

    // Check password
    if (!Hash::check($this->input('password'), $user->password)) {
      RateLimiter::hit($this->throttleKey());
      throw ValidationException::withMessages([
        'password' => 'Mật khẩu không chính xác.',
      ]);
    }

    // If credentials are correct, attempt to log in
    if (!Auth::attempt($credentials, $this->boolean('remember'))) {
      RateLimiter::hit($this->throttleKey());
      throw ValidationException::withMessages([
        'email' => trans('auth.failed'),
      ]);
    }

    RateLimiter::clear($this->throttleKey());
  }



  /**
   * Ensure the login request is not rate limited.
   *
   * @return void
   * @throws \Illuminate\Validation\ValidationException
   */
  public function ensureIsNotRateLimited(): void
  {
    if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
      return;
    }

    event(new Lockout($this));

    $seconds = RateLimiter::availableIn($this->throttleKey());

    throw ValidationException::withMessages([
      'email' => trans('auth.throttle', [
        'seconds' => $seconds,
        'minutes' => ceil($seconds / 60),
      ]),
    ]);
  }

  /**
   * Get the rate limiting throttle key for the request.
   *
   * @return string
   */
  public function throttleKey(): string
  {
    return Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
  }
}
