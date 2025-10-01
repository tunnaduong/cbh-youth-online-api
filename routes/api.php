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
  Route::post('/upload', [FileUploadController::class, 'upload']);
  Route::get('users/{username}/avatar', [UserController::class, 'getAvatar']);
  Route::post('/password/reset/verify', [ForgotPasswordController::class, 'reset']);

  Route::get('/email/verify/{verificationCode}', [VerificationController::class, 'verify']);

  Route::post('/password/reset', [ForgotPasswordController::class, 'sendResetLinkResponse']);

  // Optional authentication middleware
  Route::middleware('optional.auth')->group(function () {
    Route::get('/topics', [TopicsController::class, 'index']); // Allow both authenticated and unauthenticated access
    Route::get('/topics/{id}', [TopicsController::class, 'show']);
    Route::get('/comments/{commentId}/replies', [TopicsController::class, 'getReplies']);
    // Route to get user profile by username
    Route::get('/users/{username}/profile', [UserController::class, 'getProfile']);
    Route::get('/forum/subforums', [ForumController::class, 'getSubforumsByRole']);
    Route::get('/forum/subforums/{subforum}/topics', [ForumController::class, 'getSubforumPosts']);
  });

  Route::get('/users/{username}/online-status', [UserController::class, 'getOnlineStatus']);
  Route::get('/users/top-active', [UserController::class, 'getTop8ActiveUsers']);

  Route::post('/users/{username}/avatar', [UserController::class, 'updateAvatar']);

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


  Route::middleware('auth:sanctum')->group(function () {
    // Your routes that require authentication
    Route::delete('/user-content/{id}', [FileUploadController::class, 'destroy']);
    Route::delete('/topics/{id}', [TopicsController::class, 'destroyTopic']);
    Route::delete('/comments/{id}', [TopicsController::class, 'destroyComment']);
    Route::delete('/comments/{id}/votes', [TopicsController::class, 'destroyCommentVote']);
    Route::delete('/topics/{id}/votes', [TopicsController::class, 'destroyTopicVote']);
    Route::delete('/user/saved-topics/{id}', [TopicsController::class, 'destroySavedTopic']);
    Route::put('/users/{username}/profile', [UserController::class, 'updateProfile']);
    Route::post('/password/change', [PasswordResetController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/topics/{id}/votes', [TopicsController::class, 'registerVote']);
    Route::post('/comments/{id}/votes', [TopicsController::class, 'voteOnComment']);
    Route::post('/topics/{id}/comments', [TopicsController::class, 'addComment']);
    Route::put('/comments/{id}', [TopicsController::class, 'updateComment']);
    Route::get('/user/saved-topics', [TopicsController::class, 'getSavedTopics']);
    Route::post('/user/saved-topics', [TopicsController::class, 'saveTopicForUser']);
    Route::post('/topics', [TopicsController::class, 'store']);
    Route::post('/topics/{id}/views/authenticated', [TopicsController::class, 'registerView']);
    Route::post('/users/{username}/follow', [FollowController::class, 'follow']); // Follow a user
    Route::delete('/users/{username}/unfollow', [FollowController::class, 'unfollow']); // Unfollow a user

    // Activity routes - grouped together
    Route::prefix('activities')->group(function () {
      Route::get('/', [ActivityController::class, 'getActivities']);
      Route::get('/liked', [ActivityController::class, 'getLikedPosts']);
      Route::get('/commented', [ActivityController::class, 'getCommentedPosts']);
      Route::get('/posts', [ActivityController::class, 'getCreatedPosts']);
    });

    // User online status routes
    Route::post('/online-status', [ActivityController::class, 'updateLastActivity']);
    Route::get('/users/{username}/online-status', [ActivityController::class, 'getOnlineStatus']);

    // User Report Routes
    Route::prefix('reports')->group(function () {
      Route::post('/', [UserReportController::class, 'store']);

      // Admin only routes
      Route::middleware('role:admin')->group(function () {
        Route::get('/', [UserReportController::class, 'index']);
        Route::get('/stats', [UserReportController::class, 'getStats']);
        Route::post('/{report}/review', [UserReportController::class, 'review']);
      });
    });

    // Story routes
    Route::post('stories', [StoryController::class, 'store']);
    Route::get('stories/{story}', [StoryController::class, 'show']);
    Route::delete('stories/{story}', [StoryController::class, 'destroy']);
    Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed']);
    Route::post('stories/{story}/react', [StoryController::class, 'react']);
    Route::delete('stories/{story}/react', [StoryController::class, 'removeReaction']);

    // Chat routes
    Route::prefix('chat')->group(function () {
      Route::get('conversations', [ChatController::class, 'getConversations']);
      Route::get('conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
      Route::post('conversations', [ChatController::class, 'createPrivateConversation']);
      Route::post('conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
      Route::post('conversations/{conversationId}/read', [ChatController::class, 'markAsRead']);
      Route::delete('messages/{messageId}', [ChatController::class, 'deleteMessage']);
      Route::put('messages/{messageId}', [ChatController::class, 'editMessage']);

      // New group chat routes
      Route::post('groups', [ChatController::class, 'createGroupConversation']);
      Route::put('groups/{conversationId}', [ChatController::class, 'updateGroupConversation']);
      Route::post('groups/{conversationId}/participants', [ChatController::class, 'addGroupParticipants']);
      Route::delete('groups/{conversationId}/participants/{userId}', [ChatController::class, 'removeGroupParticipant']);

      // Search user for new conversation
      Route::get('search/users', [ChatController::class, 'searchUserForChat']);
    });
  });
  // Search routes
  Route::get('search', [SearchController::class, 'search']);
  Route::get('stories', [StoryController::class, 'index']);

  // Admin API Routes
  Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Quản lý người dùng
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::post('/users', [AdminController::class, 'storeUser']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser']);
    Route::get('/users/stats', [AdminController::class, 'userStats']);

    // Quản lý danh mục diễn đàn
    Route::get('/forum-categories', [AdminController::class, 'listForumCategories']);
    Route::post('/forum-categories', [AdminController::class, 'storeForumCategory']);
    Route::put('/forum-categories/{id}', [AdminController::class, 'updateForumCategory']);
    Route::delete('/forum-categories/{id}', [AdminController::class, 'destroyForumCategory']);

    // Quản lý diễn đàn con
    Route::get('/subforums', [AdminController::class, 'listSubforums']);
    Route::post('/subforums', [AdminController::class, 'storeSubforum']);
    Route::put('/subforums/{id}', [AdminController::class, 'updateSubforum']);
    Route::delete('/subforums/{id}', [AdminController::class, 'destroySubforum']);

    // Quản lý bài viết
    Route::get('/posts', [AdminController::class, 'listPosts']);
    Route::post('/posts', [AdminController::class, 'storePost']);
    Route::put('/posts/{id}', [AdminController::class, 'updatePost']);
    Route::delete('/posts/{id}', [AdminController::class, 'destroyPost']);
    Route::put('/posts/{id}/pin', [AdminController::class, 'togglePinPost']);
    Route::put('/posts/{id}/lock', [AdminController::class, 'toggleLockPost']);

    // Quản lý lớp học
    Route::get('/classes', [AdminController::class, 'listClasses']);
    Route::post('/classes', [AdminController::class, 'storeClass']);
    Route::put('/classes/{id}', [AdminController::class, 'updateClass']);
    Route::delete('/classes/{id}', [AdminController::class, 'destroyClass']);

    // Quản lý thời khóa biểu
    Route::get('/schedules', [AdminController::class, 'listSchedules']);
    Route::post('/schedules', [AdminController::class, 'storeSchedule']);
    Route::put('/schedules/{id}', [AdminController::class, 'updateSchedule']);
    Route::delete('/schedules/{id}', [AdminController::class, 'destroySchedule']);

    // Quản lý vi phạm học sinh
    Route::get('/violations', [AdminController::class, 'listViolations']);
    Route::post('/violations', [AdminController::class, 'storeViolation']);
    Route::put('/violations/{id}', [AdminController::class, 'updateViolation']);
    Route::delete('/violations/{id}', [AdminController::class, 'destroyViolation']);

    // Quản lý báo cáo xung kích
    Route::get('/monitor-reports', [AdminController::class, 'listMonitorReports']);
    Route::post('/monitor-reports', [AdminController::class, 'storeMonitorReport']);
    Route::put('/monitor-reports/{id}', [AdminController::class, 'updateMonitorReport']);
    Route::delete('/monitor-reports/{id}', [AdminController::class, 'destroyMonitorReport']);
  });
});
