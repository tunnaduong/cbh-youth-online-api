<?php

namespace App\Providers;

use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\DB;
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
    // Chỉ chạy khi Doctrine DBAL tồn tại
    if (class_exists(Type::class)) {
      $platform = DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform();

      // Map lại kiểu dữ liệu để tránh lỗi khi introspect MySQL
      $platform->registerDoctrineTypeMapping('timestamp', 'datetime');
      $platform->registerDoctrineTypeMapping('bit', 'boolean');
      $platform->registerDoctrineTypeMapping('enum', 'string');
    }
    // Force HTTPS in production
    if (config('app.env') != 'local') {
      URL::forceScheme('https');
      URL::forceRootUrl(config('app.url'));
    }
  }
}
