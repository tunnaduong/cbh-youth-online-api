<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * The main service provider for the application.
 */
class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   *
   * @return void
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot(): void
  {
    //
    if (config('app.env') === 'production') {
      URL::forceScheme('https');
    }

    // Audit log: what a member (or an admin on their behalf) changes on a
    // profile or a post, with the data before and after. Only the fields a
    // person edits - not counters, timestamps, or what moderation sets
    // (hidden, pinned), which would log an edit the author never made.
    // Every other action is logged by the RecordAuditLog middleware.
    \App\Models\UserProfile::updated(function ($profile) {
      \App\Models\AuditLog::recordChanges(
        'UPDATE_PROFILE',
        $profile,
        ['profile_name', 'bio', 'birthday', 'gender', 'location', 'hide_email', 'profile_picture', 'cover_photo', 'profile_theme', 'verified'],
        $profile->auth_account_id,
        'profile'
      );
    });

    \App\Models\Topic::updated(function ($topic) {
      \App\Models\AuditLog::recordChanges(
        'EDIT_POST',
        $topic,
        ['title', 'description', 'subforum_id', 'privacy', 'anonymous'],
        $topic->user_id,
        'topic'
      );
    });
  }
}
