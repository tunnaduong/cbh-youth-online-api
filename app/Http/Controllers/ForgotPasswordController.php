<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    /**
     * Customize the password reset URL for the email.
     */
    protected function sendResetLinkResponse(Request $request, $response)
    {
        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        return response()->json(['message' => trans($response)]);
    }

    protected function reset(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Find the user by email
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return redirect()->back()->with('error', 'Không tìm thấy người dùng.');
        }

        // Reset the password
        $response = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = bcrypt($password);
                $user->save();
            }
        );

        // Check for success or failure
        if ($response == Password::PASSWORD_RESET) {
            return redirect()->back()->with('status', 'Mật khẩu đã được đổi thành công.');
        }

        return redirect()->back()->with('error', 'Không thể đổi mật khẩu.');
    }
}
