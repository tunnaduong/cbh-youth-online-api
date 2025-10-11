<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\AuthAccount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * The application's authentication service provider.
 *
 * This provider is responsible for registering the application's policies
 * and any other authentication or authorization services.
 */
class AuthServiceProvider extends ServiceProvider
{
  /**
   * The model to policy mappings for the application.
   *
   * @var array<class-string, class-string>
   */
  protected $policies = [
    //
  ];

  /**
   * Register any authentication / authorization services.
   *
   * @return void
   */
  public function boot(): void
  {
    //
    Gate::define('viewApiDocs', function (?AuthAccount $user = null) {
      return true;
    });
  }
}
