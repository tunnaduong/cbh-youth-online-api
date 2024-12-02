<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Change the prefix to v1.0
Route::prefix('v1.0')->group(function () {

    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::get('users/{username}/avatar', [UserController::class, 'getAvatar']);
    Route::post('/password/reset/verify', [ForgotPasswordController::class, 'reset'])->name('password.reset');


    Route::get('/email/verify/{verificationCode}', [VerificationController::class, 'verify'])->name('verification.verify');

    Route::post('/password/reset', [ForgotPasswordController::class, 'sendResetLinkResponse']);

    // Optional authentication middleware
    Route::middleware('optional.auth')->group(function () {
        Route::get('/topics', [TopicsController::class, 'index']); // Allow both authenticated and unauthenticated access
        Route::get('/topics/{id}', [TopicsController::class, 'show']);
    });

    Route::get('/users/{username}/avatar', [UserController::class, 'getAvatar']);
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        $user = $request->user()->load('profile');

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile' => [
                'profile_name' => $user->profile->profile_name ?? null,
                'bio' => $user->profile->bio ?? null,
                'profile_picture' => $user->profile->profile_picture ?? null,
                'birthday' => $user->profile->birthday ?? null,
                'gender' => $user->profile->gender ?? null,
                'location' => $user->profile->location ?? null,
            ],
        ]);
    });

    Route::get('/forum/categories', [ForumController::class, 'getCategories']);
    Route::get('/forum/categories/{mainCategory}/subforums', [ForumController::class, 'getSubforums']);
    Route::get('/topics/pinned', [ForumController::class, 'getPinnedTopics']);

    Route::get('/user-content/{id}', [FileUploadController::class, 'show']);
    // Route::get('/topics', [TopicsController::class, 'index']); // Get list of topics

    Route::get('/topics/{id}/views', [TopicsController::class, 'getViews']);
    Route::get('/topics/{id}/votes', [TopicsController::class, 'getVotes']);
    Route::get('/topics/{id}/comments', [TopicsController::class, 'getComments']);
    // Route for getting votes for a specific comment
    Route::get('/comments/{id}/votes', [TopicsController::class, 'getVotesForComment']);

    // Allow both authenticated and unauthenticated access to register views
    Route::post('/topics/{id}/views', [TopicsController::class, 'registerView']);

    // Route to get user profile by username
    Route::get('/users/{username}/profile', [UserController::class, 'getProfile']);

    Route::middleware('auth:sanctum')->group(function () {
        // Your routes that require authentication
        Route::delete('/user-content/{id}', [FileUploadController::class, 'destroy']);
        Route::delete('/topics/{id}', [TopicsController::class, 'destroyTopic']);
        Route::delete('/comments/{id}', [TopicsController::class, 'destroyComment']);
        Route::delete('/comments/{id}/votes', [TopicsController::class, 'destroyCommentVote']);
        Route::delete('/topics/{id}/votes', [TopicsController::class, 'destroyTopicVote']);
        Route::delete('/user/saved-topics/{id}', [TopicsController::class, 'destroySavedTopic']);
        Route::put('/users/{username}/profile', [UserController::class, 'updateProfile']);
        Route::post('/users/{username}/avatar', [UserController::class, 'updateAvatar']);
        Route::post('/password/change', [PasswordResetController::class, 'changePassword'])->name('password.change');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/topics/{id}/votes', [TopicsController::class, 'registerVote']);
        Route::post('/comments/{id}/votes', [TopicsController::class, 'voteOnComment']);
        Route::post('/topics/{id}/comments', [TopicsController::class, 'addComment']);
        Route::get('/user/saved-topics', [TopicsController::class, 'getSavedTopics']);
        Route::post('/user/saved-topics', [TopicsController::class, 'saveTopicForUser']);
        Route::post('/topics', [TopicsController::class, 'store']);
        Route::post('/topics/{id}/views/authenticated', [TopicsController::class, 'registerView']);
    });
});
