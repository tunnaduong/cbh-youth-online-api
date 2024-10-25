<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\TopicsController;

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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/forums/categories', [ForumController::class, 'getCategories']);
Route::get('/forums/categories/{mainCategory}/subforums', [ForumController::class, 'getSubforums']);
Route::get('/topics/pinned', [ForumController::class, 'getPinnedTopics']);

Route::get('/topics', [TopicsController::class, 'index']); // Get list of topics
Route::post('/topics', [TopicsController::class, 'store']); // Create a new topic

Route::get('/topics/{id}/views', [TopicsController::class, 'getViews']);
Route::get('/topics/{id}/votes', [TopicsController::class, 'getVotes']);
Route::get('/topics/{id}/comments', [TopicsController::class, 'getComments']);
// Route for getting votes for a specific comment
Route::get('/comments/{id}/votes', [TopicsController::class, 'getVotesForComment']);

// Allow both authenticated and unauthenticated access to register views
Route::post('/topics/{id}/views', [TopicsController::class, 'registerView']);

Route::middleware('auth:sanctum')->group(function () {
    // Your routes that require authentication
    Route::post('/topics/{id}/votes', [TopicsController::class, 'registerVote']);
    Route::post('/comments/{id}/votes', [TopicsController::class, 'voteOnComment']);
    Route::post('/topics/{id}/comments', [TopicsController::class, 'addComment']);
    Route::get('/user/saved-topics', [TopicsController::class, 'getSavedTopics']);
    Route::post('/user/saved-topics', [TopicsController::class, 'saveTopicForUser']);
    Route::post('/topics', [TopicsController::class, 'store']);
    Route::post('/topics/{id}/views/authenticated', [TopicsController::class, 'registerView']);
});
