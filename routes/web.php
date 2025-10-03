<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\YouthNewsController;
use App\Http\Controllers\SavedPostsController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ForumCategoryController;
use App\Http\Controllers\Admin\ForumSubforumController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// --- Main Application Routes ---

// Home page
Route::get('/', [ForumController::class, 'index'])->name('home');

// Dashboard (requires authentication and email verification)
Route::get('/dashboard', function () {
  return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Standard authenticated user routes (profile management)
Route::middleware('auth')->group(function () {
  Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Feed and User Content Routes ---

// Personalized feed
Route::get('/feed', [ForumController::class, 'feed'])->name('feed');
Route::get('/api/feed', [ForumController::class, 'feedApi'])->name('feed.api');

// User avatar
Route::get('users/{username}/avatar', [UserController::class, 'getAvatar'])->name('user.avatar');

// Follow/Unfollow routes (requires authentication)
Route::middleware('auth')->group(function () {
  Route::post('/users/{username}/follow', [FollowController::class, 'follow'])->name('user.follow');
  Route::delete('/users/{username}/unfollow', [FollowController::class, 'unfollow'])->name('user.unfollow');
});

// --- Forum, Topic, and Comment Routes ---

// Publicly accessible forum routes
Route::prefix('forum')->group(function () {
  Route::get('/', [ForumController::class, 'index'])->name('forum.index');
  Route::get('/{category}/{subforum}', [ForumController::class, 'subforum'])->name('forum.subforum');
  Route::get('/{category}', [ForumController::class, 'category'])->name('forum.category');

  // Authenticated forum actions (topics, replies, comments, votes)
  Route::middleware(['auth'])->group(function () {
    // Topic Routes
    Route::get('/topic/create/{subforum}', [ForumController::class, 'createTopic'])->name('forum.topic.create');
    Route::post('/topic/store', [ForumController::class, 'storeTopic'])->name('forum.topic.store');
    Route::get('/topic/{topic}', [ForumController::class, 'showTopic'])->name('forum.topic.show');
    Route::get('/topic/{topic}/edit', [ForumController::class, 'editTopic'])->name('forum.topic.edit');
    Route::put('/topic/{topic}', [ForumController::class, 'updateTopic'])->name('forum.topic.update');
    Route::delete('/topic/{topic}', [ForumController::class, 'destroyTopic'])->name('forum.topic.destroy');

    // Reply Routes
    Route::post('/topic/{topic}/reply', [ForumController::class, 'storeReply'])->name('forum.reply.store');
    Route::put('/reply/{reply}', [ForumController::class, 'updateReply'])->name('forum.reply.update');
    Route::delete('/reply/{reply}', [ForumController::class, 'destroyReply'])->name('forum.reply.destroy');

    // Comment Routes
    Route::post('/comments', [TopicsController::class, 'addComment'])->name('comments.store');
    Route::put('/comments/{comment}', [TopicsController::class, 'updateComment'])->name('comments.update');
    Route::delete('/comments/{comment}', [TopicsController::class, 'destroyComment'])->name('comments.destroy');

    // Voting Routes
    Route::post('/topics/{topic}/vote', [TopicsController::class, 'registerVote'])->name('topics.vote');
    Route::post('/comments/{comment}/vote', [TopicsController::class, 'voteOnComment'])->name('comments.vote');
  });
});

// --- Other Feature Routes ---

// Recordings Routes
Route::get('/recordings', [RecordingController::class, 'index'])->name('recordings.index');
Route::get('/recordings/create', [RecordingController::class, 'create'])->name('recordings.create');
Route::post('/recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::get('/recordings/{recording}', [RecordingController::class, 'show'])->name('recordings.show');
Route::delete('/recordings/{recording}', [RecordingController::class, 'destroy'])->name('recordings.destroy');

// Youth News Routes
Route::get('/youth-news', [YouthNewsController::class, 'index'])->name('youth-news.index');
Route::get('/api/youth-news', [YouthNewsController::class, 'youthNewsApi'])->name('youth-news.api');

// Saved Posts Routes (requires authentication)
Route::middleware('auth')->group(function () {
  Route::get('/saved', [SavedPostsController::class, 'index'])->name('saved.index');
  Route::post('/saved', [SavedPostsController::class, 'store'])->name('saved.store');
  Route::delete('/saved/{savedPost}', [SavedPostsController::class, 'destroy'])->name('saved.destroy');
});

// API-like routes for stories (prefixed with /api but defined in web.php)
Route::middleware('auth')->prefix('api')->group(function () {
  Route::get('/stories', [StoryController::class, 'index'])->name('api.stories.index');
  Route::post('/stories', [StoryController::class, 'store'])->name('api.stories.store');
  Route::get('/stories/{story}', [StoryController::class, 'show'])->name('api.stories.show');
  Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->name('api.stories.destroy');
  Route::post('/stories/{story}/view', [StoryController::class, 'markAsViewed'])->name('api.stories.view');
  Route::post('/stories/{story}/react', [StoryController::class, 'react'])->name('api.stories.react');
  Route::delete('/stories/{story}/react', [StoryController::class, 'removeReaction'])->name('api.stories.react.remove');
});

// Topic creation from outside the main /forum prefix
Route::middleware('auth')->group(function () {
  Route::post('/topics', [TopicsController::class, 'store'])->name('topics.store');
});

// --- Dynamic User and Post Routes ---

// User Posts
Route::get('/{username}/posts/{id}', [ForumController::class, 'show'])
  ->where('id', '[0-9]+(?:-[a-z0-9-]+)?')
  ->name('posts.show');

// --- Admin Routes ---
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
  // Dashboard
  Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

  // User Management
  Route::get('/users', [AdminController::class, 'usersIndex'])->name('users.index');
  Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
  Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
  Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('users.edit');
  Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('users.update');
  Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('users.destroy');

  // Forum Category Management
  Route::resource('categories', ForumCategoryController::class, ['as' => 'admin'])
    ->except(['show']);

  // Subforum Management
  Route::resource('subforums', ForumSubforumController::class, ['as' => 'admin'])
    ->except(['show']);

  // Post Management
  Route::get('/posts', [AdminController::class, 'postsIndex'])->name('posts.index');
  Route::get('/posts/create', [AdminController::class, 'createPost'])->name('posts.create');
  Route::post('/posts', [AdminController::class, 'storePost'])->name('posts.store');
  Route::get('/posts/{id}/edit', [AdminController::class, 'editPost'])->name('posts.edit');
  Route::put('/posts/{id}', [AdminController::class, 'updatePost'])->name('posts.update');
  Route::delete('/posts/{id}', [AdminController::class, 'destroyPost'])->name('posts.destroy');
  Route::put('/posts/{id}/pin', [AdminController::class, 'togglePinPost'])->name('posts.pin');
  Route::put('/posts/{id}/lock', [AdminController::class, 'toggleLockPost'])->name('posts.lock');

  // Class Management
  Route::get('/classes', [AdminController::class, 'classesIndex'])->name('classes.index');
  Route::get('/classes/create', [AdminController::class, 'createClass'])->name('classes.create');
  Route::post('/classes', [AdminController::class, 'storeClass'])->name('classes.store');
  Route::get('/classes/{id}/edit', [AdminController::class, 'editClass'])->name('classes.edit');
  Route::put('/classes/{id}', [AdminController::class, 'updateClass'])->name('classes.update');
  Route::delete('/classes/{id}', [AdminController::class, 'destroyClass'])->name('classes.destroy');

  // Schedule Management
  Route::get('/schedules', [AdminController::class, 'schedulesIndex'])->name('schedules.index');
  Route::get('/schedules/create', [AdminController::class, 'createSchedule'])->name('schedules.create');
  Route::post('/schedules', [AdminController::class, 'storeSchedule'])->name('schedules.store');
  Route::get('/schedules/{id}/edit', [AdminController::class, 'editSchedule'])->name('schedules.edit');
  Route::put('/schedules/{id}', [AdminController::class, 'updateSchedule'])->name('schedules.update');
  Route::delete('/schedules/{id}', [AdminController::class, 'destroySchedule'])->name('schedules.destroy');

  // Student Violation Management
  Route::get('/violations', [AdminController::class, 'violationsIndex'])->name('violations.index');
  Route::get('/violations/create', [AdminController::class, 'createViolation'])->name('violations.create');
  Route::post('/violations', [AdminController::class, 'storeViolation'])->name('violations.store');
  Route::get('/violations/{id}/edit', [AdminController::class, 'editViolation'])->name('violations.edit');
  Route::put('/violations/{id}', [AdminController::class, 'updateViolation'])->name('violations.update');
  Route::delete('/violations/{id}', [AdminController::class, 'destroyViolation'])->name('violations.destroy');

  // Monitor Report Management
  Route::get('/monitor-reports', [AdminController::class, 'monitorReportsIndex'])->name('monitor-reports.index');
  Route::get('/monitor-reports/create', [AdminController::class, 'createMonitorReport'])->name('monitor-reports.create');
  Route::post('/monitor-reports', [AdminController::class, 'storeMonitorReport'])->name('monitor-reports.store');
  Route::get('/monitor-reports/{id}/edit', [AdminController::class, 'editMonitorReport'])->name('monitor-reports.edit');
  Route::put('/monitor-reports/{id}', [AdminController::class, 'updateMonitorReport'])->name('monitor-reports.update');
  Route::delete('/monitor-reports/{id}', [AdminController::class, 'destroyMonitorReport'])->name('monitor-reports.destroy');
});

// Include authentication routes
require __DIR__ . '/auth.php';

// --- General Authenticated Routes ---

// Settings routes
Route::middleware('auth')->group(function () {
  Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
  Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
  Route::delete('/settings/delete-account', [SettingsController::class, 'deleteAccount'])->name('settings.delete-account');
});

// --- Static Policy and Info Routes ---
Route::prefix('chinh-sach')->group(function () {
  // Forum rules route (redirects to a topic)
  Route::get('/noi-quy-dien-dan', function () {
    $topic = \App\Models\Topic::where('subforum_id', 10)
      ->where('user_id', 45)
      ->where('title', 'Quy định và hướng dẫn sử dụng diễn đàn CBH Youth Online')
      ->first();

    if (!$topic) {
      abort(404, 'Bài viết không tồn tại');
    }

    // Get the username for the redirect
    $username = $topic->user->username;
    $titleSlug = str()->slug($topic->title, '-');
    if (empty($titleSlug)) {
      $titleSlug = 'untitled';
    }
    $correctSlug = $topic->id . '-' . $titleSlug;

    return redirect()->route('posts.show', [
      'username' => $username,
      'id' => $correctSlug
    ]);
  })->name('policy.forum-rules');

  // Privacy policy route
  Route::get('/chinh-sach-bao-mat', function () {
    return Inertia::render('Policies/Privacy');
  })->name('policy.privacy');

  // Terms of service route
  Route::get('/dieu-khoan-su-dung', function () {
    return Inertia::render('Policies/Terms');
  })->name('policy.terms');
});

// --- Fallback Routes ---

// User profile routes (should be near the end)
Route::get('/{username}', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/{username}/{tab}', [ProfileController::class, 'showWithTab'])->name('profile.show.tab')->where('tab', 'posts|followers|following');

// Fallback route for 404 errors
Route::fallback(function () {
  return Inertia::render('Errors/404');
});
