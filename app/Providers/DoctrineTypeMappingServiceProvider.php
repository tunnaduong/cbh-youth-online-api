<?php

namespace App\Providers;

use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider to register Doctrine DBAL type mappings.
 * This provider runs early to ensure type mappings are available
 * before any database introspection occurs.
 */
class DoctrineTypeMappingServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   *
   * @return void
   */
  public function register(): void
  {
    // Register type mappings as early as possible
    $this->registerDoctrineTypeMappings();
  }

  /**
   * Bootstrap services.
   *
   * @return void
   */
  public function boot(): void
  {
    //
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
      // Get the default connection
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
      $platform->registerDoctrineTypeMapping('varchar', 'string');
      $platform->registerDoctrineTypeMapping('char', 'string');
      $platform->registerDoctrineTypeMapping('text', 'text');
      $platform->registerDoctrineTypeMapping('blob', 'blob');
      $platform->registerDoctrineTypeMapping('longblob', 'blob');
      $platform->registerDoctrineTypeMapping('mediumblob', 'blob');
      $platform->registerDoctrineTypeMapping('tinyblob', 'blob');
    } catch (\Exception $e) {
      // Log the error but don't break the application
      \Log::warning('Failed to register Doctrine type mappings: ' . $e->getMessage());
    }
  }
}
