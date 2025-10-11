<?php

namespace App\Providers;

use App\Models\AuthAccount;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
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
    Gate::define('viewApiDocs', function () {
      return true;
    });
  }
}
