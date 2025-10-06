<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;
use App\Providers\RouteServiceProvider;

class RegisteredUserController extends Controller
{
  /**
   * Display the registration view.
   */
  public function create(): Response
  {
    return response()->json(['message' => 'Registration form data']);
  }

  /**
   * Handle an incoming registration request.
   *
   * @throws \Illuminate\Validation\ValidationException
   */
  public function store(Request $request): RedirectResponse
  {
    $request->validate([
      'username' => 'required|string|max:255|unique:' . AuthAccount::class . ',username',
      'profile_name' => 'required|string|max:255',
      'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
      'password' => ['required', 'confirmed', Rules\Password::defaults()],
      'agree_terms' => 'required|accepted',
      'recaptcha' => [
        'required',
        function ($attribute, $value, $fail) {
          $client = new \GuzzleHttp\Client();

          try {
            $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
              'form_params' => [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $value,
              ]
            ]);

            $body = json_decode((string) $response->getBody());

            if (!$body->success) {
              $fail('The reCAPTCHA verification failed. Please try again.');
            }
          } catch (\Exception $e) {
            $fail('Error validating reCAPTCHA. Please try again.');
          }
        }
      ],
    ], [
      // Username
      'username.required' => 'Tên đăng nhập không được bỏ trống.',
      'username.string' => 'Tên đăng nhập phải là chuỗi.',
      'username.max' => 'Tên đăng nhập không được vượt quá 255 ký tự.',
      'username.unique' => 'Tên đăng nhập đã tồn tại.',

      // Profile name
      'profile_name.required' => 'Họ và tên không được bỏ trống.',
      'profile_name.string' => 'Họ và tên phải là chuỗi.',
      'profile_name.max' => 'Họ và tên không được vượt quá 255 ký tự.',

      // Email
      'email.required' => 'Email không được bỏ trống.',
      'email.string' => 'Email phải là chuỗi.',
      'email.lowercase' => 'Email phải ở dạng chữ thường.',
      'email.email' => 'Email không hợp lệ.',
      'email.max' => 'Email không được vượt quá 255 ký tự.',
      'email.unique' => 'Email đã được sử dụng.',

      // Password
      'password.required' => 'Mật khẩu không được bỏ trống.',
      'password.confirmed' => 'Xác nhận mật khẩu không khớp.',

      // Agree terms
      'agree_terms.required' => 'Bạn phải đồng ý với điều khoản.',
      'agree_terms.accepted' => 'Bạn phải chấp nhận điều khoản.',

      // Recaptcha (rule inline đã có fail message rồi)
      'recaptcha.required' => 'Vui lòng xác minh reCAPTCHA.',
    ]);

    $account = AuthAccount::create([
      'username' => $request->username,
      'password' => Hash::make($request->password),
      'email' => $request->email,
    ]);

    UserProfile::create([
      'auth_account_id' => $account->id,
      'profile_username' => $account->username,
      'profile_name' => $request->profile_name,
    ]);

    event(new Registered($account));

    Auth::login($account);

    return redirect(RouteServiceProvider::HOME);
  }
}
