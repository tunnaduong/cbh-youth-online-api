<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordResetController;

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

Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');

Route::get('/', function () {
    return view('welcome');
});
