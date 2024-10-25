<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

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
}
