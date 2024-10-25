<?php

use App\Http\Controllers\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// web.php
Route::get('/password/reset/{token}', function ($token) {
    // Return the password reset form view
    return view('auth.passwords.reset')->with(['token' => $token, 'email' => request('email')]);
})->name('password.reset');

use App\Http\Controllers\Auth\VerificationController;

Route::get('email/verify/{verificationCode}', [VerificationController::class, 'verify'])->name('verification.verify');

Route::post('password/reset', [ForgotPasswordController::class, 'reset'])->name('password.update');

Route::get('/', function () {
    return view('welcome');
});
