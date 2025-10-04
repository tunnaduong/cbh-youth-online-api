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
    // Đảm bảo Doctrine DBAL nhận diện 'timestamp'
    $this->app->resolving('db', function ($db) {
      try {
        $platform = $db->connection()->getDoctrineSchemaManager()->getDatabasePlatform();
        if ($platform instanceof AbstractPlatform) {
          $platform->registerDoctrineTypeMapping('timestamp', 'datetime');
        }
      } catch (\Throwable $e) {
        // Bỏ qua nếu kết nối DB chưa sẵn sàng
      }
    });
  }

  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot(): void
  {
    // Register Doctrine DBAL type mappings
    $this->registerDoctrineTypeMappings();

    // Force HTTPS in production
    if (config('app.env') != 'local') {
      URL::forceScheme('https');
      URL::forceRootUrl(config('app.url'));
    }
  }

  /**
   * Register Doctrine DBAL type mappings to handle MySQL specific types.
   *
   * @return void
   */
  private function registerDoctrineTypeMappings(): void
  {
    // Only register if Doctrine DBAL is available
    if (!class_exists(Type::class)) {
      return;
    }

    try {
      $connection = DB::connection();
      $platform = $connection->getDoctrineConnection()->getDatabasePlatform();

      // Map MySQL specific types to Doctrine types
      $platform->registerDoctrineTypeMapping('timestamp', 'datetime');
      $platform->registerDoctrineTypeMapping('bit', 'boolean');
      $platform->registerDoctrineTypeMapping('enum', 'string');
      $platform->registerDoctrineTypeMapping('json', 'json');
      $platform->registerDoctrineTypeMapping('longtext', 'text');
      $platform->registerDoctrineTypeMapping('mediumtext', 'text');
      $platform->registerDoctrineTypeMapping('tinytext', 'text');
    } catch (\Exception $e) {
      // Log the error but don't break the application
      \Log::warning('Failed to register Doctrine type mappings: ' . $e->getMessage());
    }
  }
}
