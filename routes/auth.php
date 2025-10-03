<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Routes for guest users (not authenticated)
Route::middleware('guest')->group(function () {
    // Display registration form
    Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

    // Handle registration form submission
    Route::post('register', [RegisteredUserController::class, 'store']);

    // Display login form
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

    // Handle login form submission
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Display forgot password form
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

    // Handle forgot password form submission
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');

    // Display password reset form
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

    // Handle password reset form submission
    Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.store');
});

// Routes for authenticated users
Route::middleware('auth')->group(function () {
    // Display email verification notice
    Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

    // Handle email verification link click
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    // Send a new email verification notification
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    // Display confirm password form
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

    // Handle confirm password form submission
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Handle password update
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // Handle logout
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
});
