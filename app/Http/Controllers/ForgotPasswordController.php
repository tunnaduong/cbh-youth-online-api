<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Handles the logic for password reset requests.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Send a password reset link to the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkResponse(Request $request)
    {
        // Validate the email input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        // Find the user to get the email
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản với email này.'], 404); // Not Found
        }

        // Generate the reset token
        $token = Password::broker()->createToken($user);

        // Send the reset password email manually
        Mail::to($user->email)->send(new ResetPasswordMail($token, $user->email));

        // Optionally, you can also save the token in the password resets table, but Laravel handles this automatically if you are using the broker.
        Password::broker()->sendResetLink($request->only('email')); // Optional if you want to keep the built-in behavior

        return response()->json(['status' => 'success', 'message' => 'Email reset mật khẩu đã được gửi.']);
    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        // Find the user by email
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy người dùng.'], 404); // Not Found
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
            return response()->json(['status' => 'success', 'message' => 'Mật khẩu đã được reset thành công.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Không thể reset mật khẩu.'], 500); // Internal Server Error
    }
}
