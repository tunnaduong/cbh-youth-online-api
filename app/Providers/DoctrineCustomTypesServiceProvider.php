<?php

namespace App\Providers;

use Doctrine\DBAL\Types\Type;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider to register custom Doctrine DBAL types.
 * This ensures all custom types are registered before any database operations.
 */
class DoctrineCustomTypesServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   *
   * @return void
   */
  public function register(): void
  {
    $this->registerCustomDoctrineTypes();
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
   * Register custom Doctrine DBAL types.
   *
   * @return void
   */
  private function registerCustomDoctrineTypes(): void
  {
    // Only register if Doctrine DBAL is available
    if (!class_exists(Type::class)) {
      return;
    }

    try {
      // Register custom types if they don't exist
      if (!Type::hasType('mysql_timestamp')) {
        Type::addType('mysql_timestamp', \Doctrine\DBAL\Types\DateTimeType::class);
      }

      if (!Type::hasType('mysql_bit')) {
        Type::addType('mysql_bit', \Doctrine\DBAL\Types\BooleanType::class);
      }

      if (!Type::hasType('mysql_enum')) {
        Type::addType('mysql_enum', \Doctrine\DBAL\Types\StringType::class);
      }
    } catch (\Exception $e) {
      // Log the error but don't break the application
      \Log::warning('Failed to register custom Doctrine types: ' . $e->getMessage());
    }
  }
}
