<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test', function () {
    return Inertia::render('Home', [
        'title' => 'Test hihihi',
    ]);
});

// Admin Routes với InertiaJS
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Quản lý người dùng
    Route::get('/users', [AdminController::class, 'usersIndex'])->name('users.index');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('users.destroy');

    // Quản lý danh mục diễn đàn
    Route::get('/forum-categories', [AdminController::class, 'forumCategoriesIndex'])->name('forum-categories.index');
    Route::get('/forum-categories/create', [AdminController::class, 'createForumCategory'])->name('forum-categories.create');
    Route::post('/forum-categories', [AdminController::class, 'storeForumCategory'])->name('forum-categories.store');
    Route::get('/forum-categories/{id}/edit', [AdminController::class, 'editForumCategory'])->name('forum-categories.edit');
    Route::put('/forum-categories/{id}', [AdminController::class, 'updateForumCategory'])->name('forum-categories.update');
    Route::delete('/forum-categories/{id}', [AdminController::class, 'destroyForumCategory'])->name('forum-categories.destroy');

    // Quản lý diễn đàn con
    Route::get('/subforums', [AdminController::class, 'subforumsIndex'])->name('subforums.index');
    Route::get('/subforums/create', [AdminController::class, 'createSubforum'])->name('subforums.create');
    Route::post('/subforums', [AdminController::class, 'storeSubforum'])->name('subforums.store');
    Route::get('/subforums/{id}/edit', [AdminController::class, 'editSubforum'])->name('subforums.edit');
    Route::put('/subforums/{id}', [AdminController::class, 'updateSubforum'])->name('subforums.update');
    Route::delete('/subforums/{id}', [AdminController::class, 'destroySubforum'])->name('subforums.destroy');

    // Quản lý bài viết
    Route::get('/posts', [AdminController::class, 'postsIndex'])->name('posts.index');
    Route::get('/posts/create', [AdminController::class, 'createPost'])->name('posts.create');
    Route::post('/posts', [AdminController::class, 'storePost'])->name('posts.store');
    Route::get('/posts/{id}/edit', [AdminController::class, 'editPost'])->name('posts.edit');
    Route::put('/posts/{id}', [AdminController::class, 'updatePost'])->name('posts.update');
    Route::delete('/posts/{id}', [AdminController::class, 'destroyPost'])->name('posts.destroy');
    Route::put('/posts/{id}/pin', [AdminController::class, 'togglePinPost'])->name('posts.pin');
    Route::put('/posts/{id}/lock', [AdminController::class, 'toggleLockPost'])->name('posts.lock');

    // Quản lý lớp học
    Route::get('/classes', [AdminController::class, 'classesIndex'])->name('classes.index');
    Route::get('/classes/create', [AdminController::class, 'createClass'])->name('classes.create');
    Route::post('/classes', [AdminController::class, 'storeClass'])->name('classes.store');
    Route::get('/classes/{id}/edit', [AdminController::class, 'editClass'])->name('classes.edit');
    Route::put('/classes/{id}', [AdminController::class, 'updateClass'])->name('classes.update');
    Route::delete('/classes/{id}', [AdminController::class, 'destroyClass'])->name('classes.destroy');

    // Quản lý thời khóa biểu
    Route::get('/schedules', [AdminController::class, 'schedulesIndex'])->name('schedules.index');
    Route::get('/schedules/create', [AdminController::class, 'createSchedule'])->name('schedules.create');
    Route::post('/schedules', [AdminController::class, 'storeSchedule'])->name('schedules.store');
    Route::get('/schedules/{id}/edit', [AdminController::class, 'editSchedule'])->name('schedules.edit');
    Route::put('/schedules/{id}', [AdminController::class, 'updateSchedule'])->name('schedules.update');
    Route::delete('/schedules/{id}', [AdminController::class, 'destroySchedule'])->name('schedules.destroy');

    // Quản lý vi phạm học sinh
    Route::get('/violations', [AdminController::class, 'violationsIndex'])->name('violations.index');
    Route::get('/violations/create', [AdminController::class, 'createViolation'])->name('violations.create');
    Route::post('/violations', [AdminController::class, 'storeViolation'])->name('violations.store');
    Route::get('/violations/{id}/edit', [AdminController::class, 'editViolation'])->name('violations.edit');
    Route::put('/violations/{id}', [AdminController::class, 'updateViolation'])->name('violations.update');
    Route::delete('/violations/{id}', [AdminController::class, 'destroyViolation'])->name('violations.destroy');

    // Quản lý báo cáo xung kích
    Route::get('/monitor-reports', [AdminController::class, 'monitorReportsIndex'])->name('monitor-reports.index');
    Route::get('/monitor-reports/create', [AdminController::class, 'createMonitorReport'])->name('monitor-reports.create');
    Route::post('/monitor-reports', [AdminController::class, 'storeMonitorReport'])->name('monitor-reports.store');
    Route::get('/monitor-reports/{id}/edit', [AdminController::class, 'editMonitorReport'])->name('monitor-reports.edit');
    Route::put('/monitor-reports/{id}', [AdminController::class, 'updateMonitorReport'])->name('monitor-reports.update');
    Route::delete('/monitor-reports/{id}', [AdminController::class, 'destroyMonitorReport'])->name('monitor-reports.destroy');
});

require __DIR__.'/auth.php';
