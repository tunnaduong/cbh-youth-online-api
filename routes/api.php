<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OnlineUserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SEPayWebhookController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\StudyMaterialCategoryController;
use App\Http\Controllers\StudyMaterialController;
use App\Http\Controllers\StudyMaterialRatingController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\UserBlockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPointDeductionController;
use App\Http\Controllers\UserReportController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\YouthNewsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "api" middleware group. Make something great!
 * |
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
  Route::post('/login/oauth', [AuthController::class, 'loginWithProvider']);
  Route::post('/oauth/exchange', [AuthController::class, 'exchangeOAuthCode']);
  Route::get('/oauth/callback', [AuthController::class, 'oauthCallback']);
  Route::post('/password/reset/verify', [ForgotPasswordController::class, 'reset']);
  Route::get('/email/verify/{verificationCode}', [VerificationController::class, 'verify']);
  Route::post('/password/reset', [ForgotPasswordController::class, 'sendResetLinkResponse']);
  Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);

  // Forum & Topic Browsing
  Route::get('/forum/categories', [ForumController::class, 'getCategories']);
  Route::get('/forum/categories/{mainCategory}/subforums', [ForumController::class, 'getSubforums']);
  Route::get('/topics/pinned', [ForumController::class, 'getPinnedTopics']);
  Route::get('/topics/sitemap', [TopicsController::class, 'getSitemapTopics']);
  Route::get('/topics/{id}/views', [TopicsController::class, 'getViews']);
  Route::get('/topics/{id}/votes', [TopicsController::class, 'getVotes']);
  Route::get('/topics/{id}/comments', [TopicsController::class, 'getComments']);
  Route::get('/comments/{id}/votes', [TopicsController::class, 'getVotesForComment']);
  Route::post('/topics/{id}/views', [TopicsController::class, 'registerView']);
  Route::get('/post-url', [ForumController::class, 'getPostUrl']);

  // User Information
  Route::get('/users/{username}/online-status', [UserController::class, 'getOnlineStatus']);
  Route::get('/users/top-active', [UserController::class, 'getTop8ActiveUsers']);
  Route::get('/users/ranking', [PointsController::class, 'getTopUsers']);

  // Search & Stories
  Route::get('search', [SearchController::class, 'search']);
  Route::get('stories', [StoryController::class, 'index']);

  // Study Materials (public routes with optional auth to detect purchases)
  Route::match(['get', 'head'], '/study-materials/documents/view', [StudyMaterialController::class, 'viewDocument']);
  Route::middleware('optional.auth')->group(function () {
    Route::get('/study-materials', [StudyMaterialController::class, 'index']);
    Route::get('/study-materials/{id}', [StudyMaterialController::class, 'show']);
    Route::get('/study-materials/{id}/preview', [StudyMaterialController::class, 'getPreview']);
    Route::get('/users/{username}/study-materials', [StudyMaterialController::class, 'getUserMaterials']);
    Route::get('/study-materials/{id}/ratings', [StudyMaterialRatingController::class, 'getRatings']);
    Route::get('/study-material-categories', [StudyMaterialCategoryController::class, 'index']);
    Route::get('/study-material-categories/{id}', [StudyMaterialCategoryController::class, 'show']);
    Route::post('/study-materials/{id}/view', [StudyMaterialController::class, 'view']);
  });

  // Webhook (public, no auth)
  Route::post('/webhooks/sepay', [SEPayWebhookController::class, 'handleWebhook']);

  // Push Notification VAPID Public Key (public endpoint)
  Route::get('/notifications/vapid-public-key', [NotificationController::class, 'getVapidPublicKey']);

  // Public Chat (accessible to everyone, but can check auth if token provided)
  Route::middleware('optional.auth')->prefix('chat/public')->group(function () {
    Route::get('messages', [ChatController::class, 'getPublicChatMessages']);
    Route::post('messages', [ChatController::class, 'sendPublicMessage']);
    Route::get('participants', [ChatController::class, 'getPublicChatParticipants']);
  });

  // Online Users (Public)
  Route::get('/online-users/stats', [OnlineUserController::class, 'getStats']);
  Route::get('/online-users/max', [OnlineUserController::class, 'getMaxOnline']);

  // --- OPTIONAL AUTHENTICATION ROUTES ---
  // These routes can be accessed by guests, but provide additional data for authenticated users.
  Route::middleware('optional.auth')->group(function () {
    Route::get('/youth-news', [YouthNewsController::class, 'index']);

    // Recordings
    Route::get('/recordings', [RecordingController::class, 'index']);

    Route::get('/forum-data', function () {
      $user = auth()->user();

      $mainCategories = $user && $user->role == 'admin'
        ? \App\Models\ForumMainCategory::select('id', 'name', 'arrange')
          ->with([
            'subforums' => function ($query) {
              $query
                ->select('id', 'name', 'main_category_id', 'arrange')
                ->orderBy('arrange', 'asc');
            }
          ])
          ->orderBy('arrange', 'asc')
          ->get()
        : \App\Models\ForumMainCategory::select('id', 'name', 'arrange')
          ->with([
            'subforums' => function ($query) {
              $query
                ->select('id', 'name', 'main_category_id', 'arrange')
                ->orderBy('arrange', 'asc');
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
      $userPoints = $user->getPoints();

      // Calculate user ranking
      $rank = \App\Models\AuthAccount::where('role', '!=', 'admin')
        ->where('points', '>', $userPoints)
        ->count() + 1;

      return response()->json([
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'profile_name' => $user->profile->profile_name ?? null,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'email_verified_at' => $user->email_verified_at ?? null,
        'verified' => $user->verified ?? false,
        'role' => $user->role ?? null,
        'total_points' => $userPoints,
        'rank' => $rank,
      ]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/delete-account', [UserController::class, 'deleteAccount']);
    Route::post('/users/{username}/avatar', [UserController::class, 'updateAvatar']);
    Route::put('/users/{username}/profile', [UserController::class, 'updateProfile']);
    Route::post('/password/change', [PasswordResetController::class, 'changePassword']);
    Route::post('/email/resend-verification', [VerificationController::class, 'resend']);
    Route::post('/users/{username}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{username}/unfollow', [FollowController::class, 'unfollow']);
    Route::post('/online-status', [ActivityController::class, 'updateLastActivity']);

    // Notification Settings
    Route::get('/notification-settings', [NotificationSettingsController::class, 'getSettings']);
    Route::put('/notification-settings', [NotificationSettingsController::class, 'updateSettings']);

    // Notifications
    Route::prefix('notifications')->group(function () {
      Route::get('/', [NotificationController::class, 'index']);
      Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
      Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
      Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);

      // Push notification subscriptions (must be before /{id} route to avoid route conflict)
      Route::post('/subscribe', [NotificationController::class, 'subscribe']);
      Route::delete('/unsubscribe', [NotificationController::class, 'unsubscribe']);
      Route::get('/subscriptions', [NotificationController::class, 'getSubscriptions']);

      // Expo push token registration
      Route::prefix('expo')->group(function () {
        Route::post('/register', [NotificationController::class, 'registerExpoPushToken']);
        Route::delete('/unregister', [NotificationController::class, 'unregisterExpoPushToken']);
        Route::get('/tokens', [NotificationController::class, 'getExpoPushTokens']);
      });

      // Delete notification (must be after specific routes like /unsubscribe)
      Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Topics & Content
    Route::post('/topics', [TopicsController::class, 'store']);
    Route::post('/topics/{id}/votes', [TopicsController::class, 'registerVote']);
    Route::get('/user/saved-topics', [TopicsController::class, 'getSavedTopics']);
    Route::post('/user/saved-topics', [TopicsController::class, 'saveTopicForUser']);
    Route::delete('/user/saved-topics/{id}', [TopicsController::class, 'destroySavedTopic']);
    Route::delete('/user-content/{id}', [FileUploadController::class, 'destroy']);
    Route::delete('/topics/{id}', [TopicsController::class, 'destroyTopic']);
    Route::put('/topics/{id}', [TopicsController::class, 'update']);
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

    // User Blocking
    Route::post('/users/block', [UserBlockController::class, 'store']);
    Route::post('/users/unblock', [UserBlockController::class, 'destroy']);
    Route::get('/users/blocked', [UserBlockController::class, 'index']);

    // Stories
    Route::post('stories', [StoryController::class, 'store']);
    Route::get('stories/archive', [StoryController::class, 'getArchive']);  // Must be before stories/{story}
    Route::get('stories/{story}', [StoryController::class, 'show']);
    Route::delete('stories/{story}', [StoryController::class, 'destroy']);
    Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed']);
    Route::post('stories/{story}/react', [StoryController::class, 'react']);
    Route::delete('stories/{story}/react', [StoryController::class, 'removeReaction']);
    Route::post('stories/{story}/reply', [StoryController::class, 'reply']);
    Route::get('stories/{story}/viewers', [StoryController::class, 'getViewers']);

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

    // Study Materials
    Route::post('/study-materials', [StudyMaterialController::class, 'store']);
    Route::put('/study-materials/{id}', [StudyMaterialController::class, 'update']);
    Route::delete('/study-materials/{id}', [StudyMaterialController::class, 'destroy']);
    Route::post('/study-materials/{id}/purchase', [StudyMaterialController::class, 'purchase']);
    Route::get('/study-materials/{id}/download', [StudyMaterialController::class, 'download']);

    // Study Material Categories (admin only)
    Route::middleware('role:admin')->group(function () {
      Route::post('/study-material-categories', [StudyMaterialCategoryController::class, 'store']);
      Route::put('/study-material-categories/{id}', [StudyMaterialCategoryController::class, 'update']);
      Route::delete('/study-material-categories/{id}', [StudyMaterialCategoryController::class, 'destroy']);
    });

    // Study Material Ratings
    Route::post('/study-materials/{id}/ratings', [StudyMaterialRatingController::class, 'store']);
    Route::put('/ratings/{id}', [StudyMaterialRatingController::class, 'update']);
    Route::delete('/ratings/{id}', [StudyMaterialRatingController::class, 'destroy']);

    // Wallet
    Route::get('/wallet/balance', [WalletController::class, 'getBalance']);
    Route::get('/wallet/transactions', [WalletController::class, 'getTransactions']);
    Route::post('/wallet/deposit-request', [WalletController::class, 'createDepositRequest']);
    Route::post('/wallet/withdrawal-request', [WalletController::class, 'requestWithdrawal']);
    Route::get('/wallet/withdrawal-requests', [WalletController::class, 'getWithdrawalRequests']);
    Route::post('/wallet/withdrawal-requests/{id}/cancel', [WalletController::class, 'cancelWithdrawalRequest']);
    Route::get('/wallet/withdrawal-history', [WalletController::class, 'getWithdrawalHistory']);

    // Admin routes
    Route::middleware('role:admin')->group(function () {
      Route::get('/admin/withdrawal-requests/pending', [AdminController::class, 'getPendingWithdrawals']);
      Route::post('/admin/withdrawal-requests/{id}/approve', [AdminController::class, 'approveWithdrawal']);
      Route::post('/admin/withdrawal-requests/{id}/reject', [AdminController::class, 'rejectWithdrawal']);
    });

    // SePay Webhook
    Route::post('/hooks/sepay-payment', [\SePay\SePay\Http\Controllers\SePayController::class, 'webhook']);
  });
});
