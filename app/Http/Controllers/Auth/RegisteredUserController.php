<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Mail\VerifyEmail;
use App\Models\AuthAccount;
use App\Models\UserProfile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;
use App\Providers\RouteServiceProvider;
use App\Models\AuthEmailVerificationCode;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
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
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
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
        ]);

        $account = AuthAccount::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'email' => $request->email,
        ]);

        UserProfile::create([
            'auth_account_id' => $account->id, // Assuming 'user_id' is a foreign key in cyo_user_profiles
            'profile_username' => $account->username, // Or other default values
            'profile_name' => $request->name,
        ]);

        event(new Registered($account));

        Auth::login($account);

        return redirect(RouteServiceProvider::HOME);
    }
}
