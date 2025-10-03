<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles the changing of a user's password.
 */
class PasswordResetController extends Controller
{
    /**
     * Change the password for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function changePassword(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(["message" => "Bạn cần phải đăng nhập để đổi mật khẩu"], 401);
        }

        // Check if the current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu bạn cung cấp không giống với mật khẩu hiện tại.'],
            ]);
        }

        // Update the user's password
        $user->password = Hash::make($request->new_password);
        /** @var \App\Models\AuthAccount $user **/
        $user->save();

        return response()->json(["message" => "Đổi mật khẩu thành công!"], 201);
    }
}
