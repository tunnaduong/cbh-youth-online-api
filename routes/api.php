<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\UserReportController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserPointDeductionController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\OnlineUserController;

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

Route::prefix('v1.0')->group(function () {
  // --- PUBLIC ROUTES ---
  // These routes are accessible to everyone, authenticated or not.

  // Home Route
  Route::get('/home', [ForumController::class, 'index']);

  // File and User Content
  Route::post('/upload', [FileUploadController::class, 'upload']);
  Route::get('users/{username}/avatar', [UserController::class, 'getAvatar']);
  Route::get('/user-content/{id}', [FileUploadController::class, 'show']);

  // Authentication & Password Reset
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/password/reset/verify', [ForgotPasswordController::class, 'reset']);
  Route::get('/email/verify/{verificationCode}', [VerificationController::class, 'verify']);
  Route::post('/password/reset', [ForgotPasswordController::class, 'sendResetLinkResponse']);
  Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);

  // Forum & Topic Browsing
  Route::get('/forum/categories', [ForumController::class, 'getCategories']);
  Route::get('/forum/categories/{mainCategory}/subforums', [ForumController::class, 'getSubforums']);
  Route::get('/topics/pinned', [ForumController::class, 'getPinnedTopics']);
  Route::get('/topics/{id}/views', [TopicsController::class, 'getViews']);
  Route::get('/topics/{id}/votes', [TopicsController::class, 'getVotes']);
  Route::get('/topics/{id}/comments', [TopicsController::class, 'getComments']);
  Route::get('/comments/{id}/votes', [TopicsController::class, 'getVotesForComment']);
  Route::post('/topics/{id}/views', [TopicsController::class, 'registerView']);

  // User Information
  Route::get('/users/{username}/online-status', [UserController::class, 'getOnlineStatus']);
  Route::get('/users/top-active', [UserController::class, 'getTop8ActiveUsers']);
  Route::get('/users/ranking', [PointsController::class, 'getTopUsers']);

  // Search & Stories
  Route::get('search', [SearchController::class, 'search']);
  Route::get('stories', [StoryController::class, 'index']);

  // Online Users (Public)
  Route::get('/online-users/stats', [OnlineUserController::class, 'getStats']);
  Route::get('/online-users/max', [OnlineUserController::class, 'getMaxOnline']);

  // --- OPTIONAL AUTHENTICATION ROUTES ---
  // These routes can be accessed by guests, but provide additional data for authenticated users.
  Route::middleware('optional.auth')->group(function () {
    Route::get('/forum-data', function () {
      $user = auth()->user();

      $mainCategories = $user && $user->role == 'admin' ?
        \App\Models\ForumMainCategory::select('id', 'name', 'arrange')
          ->with([
            'subForums' => function ($query) {
              $query->select('id', 'name', 'main_category_id');
            }
          ])
          ->orderBy('arrange', 'asc')
          ->get() :
        \App\Models\ForumMainCategory::select('id', 'name', 'arrange')
          ->with([
            'subForums' => function ($query) {
              $query->select('id', 'name', 'main_category_id');
            }
          ])
          ->where('role_restriction', '!=', 'admin')
          ->orderBy('arrange', 'asc')
          ->get();

      return response()->json([
        'main_categories' => $mainCategories
      ]);
    });
    Route::get('/topics', [TopicsController::class, 'index']);
    Route::get('/topics/{id}', [TopicsController::class, 'show']);
    Route::get('/comments/{commentId}/replies', [TopicsController::class, 'getReplies']);
    Route::get('/users/{username}/profile', [UserController::class, 'getProfile']);
    Route::get('/forum/subforums', [ForumController::class, 'getSubforumsByRole']);
    Route::get('/forum/subforums/{subforum}/topics', [ForumController::class, 'getSubforumPosts']);
    Route::post('/online-users/track', [OnlineUserController::class, 'track']);
  });


  // --- AUTHENTICATION REQUIRED ROUTES ---
  // These routes require a valid Sanctum authentication token.
  Route::middleware('auth:sanctum')->group(function () {
    // User & Profile
    Route::get('/user', function (Request $request) {
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
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/users/{username}/avatar', [UserController::class, 'updateAvatar']);
    Route::put('/users/{username}/profile', [UserController::class, 'updateProfile']);
    Route::post('/password/change', [PasswordResetController::class, 'changePassword']);
    Route::post('/users/{username}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{username}/unfollow', [FollowController::class, 'unfollow']);
    Route::post('/online-status', [ActivityController::class, 'updateLastActivity']);

    // Topics & Content
    Route::post('/topics', [TopicsController::class, 'store']);
    Route::post('/topics/{id}/votes', [TopicsController::class, 'registerVote']);
    Route::get('/user/saved-topics', [TopicsController::class, 'getSavedTopics']);
    Route::post('/user/saved-topics', [TopicsController::class, 'saveTopicForUser']);
    Route::delete('/user/saved-topics/{id}', [TopicsController::class, 'destroySavedTopic']);
    Route::delete('/user-content/{id}', [FileUploadController::class, 'destroy']);
    Route::delete('/topics/{id}', [TopicsController::class, 'destroyTopic']);
    Route::delete('/topics/{id}/votes', [TopicsController::class, 'destroyTopicVote']);


    // Comments
    Route::post('/topics/{id}/comments', [TopicsController::class, 'addComment']);
    Route::put('/comments/{id}', [TopicsController::class, 'updateComment']);
    Route::delete('/comments/{id}', [TopicsController::class, 'destroyComment']);
    Route::post('/comments/{id}/votes', [TopicsController::class, 'voteOnComment']);
    Route::delete('/comments/{id}/votes', [TopicsController::class, 'destroyCommentVote']);

    // Activity Feed
    Route::prefix('activities')->group(function () {
      Route::get('/', [ActivityController::class, 'getActivities']);
      Route::get('/liked', [ActivityController::class, 'getLikedPosts']);
      Route::get('/commented', [ActivityController::class, 'getCommentedPosts']);
      Route::get('/posts', [ActivityController::class, 'getCreatedPosts']);
    });

    // User Reports
    Route::prefix('reports')->group(function () {
      Route::post('/', [UserReportController::class, 'store']);
      Route::middleware('role:admin')->group(function () {
        Route::get('/', [UserReportController::class, 'index']);
        Route::get('/stats', [UserReportController::class, 'getStats']);
        Route::post('/{report}/review', [UserReportController::class, 'review']);
      });
    });

    // Stories
    Route::post('stories', [StoryController::class, 'store']);
    Route::get('stories/{story}', [StoryController::class, 'show']);
    Route::delete('stories/{story}', [StoryController::class, 'destroy']);
    Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed']);
    Route::post('stories/{story}/react', [StoryController::class, 'react']);
    Route::delete('stories/{story}/react', [StoryController::class, 'removeReaction']);

    // Chat
    Route::prefix('chat')->group(function () {
      Route::get('conversations', [ChatController::class, 'getConversations']);
      Route::get('conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
      Route::post('conversations', [ChatController::class, 'createPrivateConversation']);
      Route::post('conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
      Route::post('conversations/{conversationId}/read', [ChatController::class, 'markAsRead']);
      Route::delete('messages/{messageId}', [ChatController::class, 'deleteMessage']);
      Route::put('messages/{messageId}', [ChatController::class, 'editMessage']);
      Route::post('groups', [ChatController::class, 'createGroupConversation']);
      Route::put('groups/{conversationId}', [ChatController::class, 'updateGroupConversation']);
      Route::post('groups/{conversationId}/participants', [ChatController::class, 'addGroupParticipants']);
      Route::delete('groups/{conversationId}/participants/{userId}', [ChatController::class, 'removeGroupParticipant']);
      Route::get('search/users', [ChatController::class, 'searchUserForChat']);
    });
  });
});
