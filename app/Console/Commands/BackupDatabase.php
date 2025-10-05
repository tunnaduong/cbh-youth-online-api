<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * A console command to create a backup of the database.
 */
class BackupDatabase extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'db:backup {--path= : Custom backup path}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Create a backup of the database (supports MySQL and PostgreSQL)';

  /**
   * Execute the console command.
   *
   * @return void
   */
  public function handle()
  {
    $this->info('Starting database backup...');

    // Get current database connection
    $connection = config('database.default');
    $dbConfig = config("database.connections.{$connection}");

    // Check if required tools are available
    if (!$this->checkRequiredTools($connection)) {
      return;
    }

    // Create backup directory if it doesn't exist
    $backupDir = $this->option('path') ?: storage_path('backups');
    if (!is_dir($backupDir)) {
      mkdir($backupDir, 0755, true);
    }

    // Generate backup filename with timestamp
    $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
    $extension = $connection === 'pgsql' ? 'sql' : 'sql';
    $backupFile = $backupDir . '/backup_' . $dbConfig['database'] . '_' . $timestamp . '.' . $extension;

    $this->info("Creating backup: {$backupFile}");

    // Execute backup based on database type
    if ($connection === 'pgsql') {
      $this->backupPostgreSQL($dbConfig, $backupFile);
    } elseif ($connection === 'mysql') {
      $this->backupMySQL($dbConfig, $backupFile);
    } else {
      $this->error("❌ Unsupported database type: {$connection}");
      $this->error("Supported databases: MySQL, PostgreSQL");
      return;
    }

    // Show recent backups
    $this->showRecentBackups($backupDir);
  }

  /**
   * Check if required database tools are available.
   *
   * @param string $connection
   * @return bool
   */
  private function checkRequiredTools($connection)
  {
    if ($connection === 'pgsql') {
      $output = [];
      $returnCode = 0;
      exec('which pg_dump', $output, $returnCode);

      if ($returnCode !== 0) {
        $this->error("❌ PostgreSQL tools not found!");
        $this->error("Please install PostgreSQL client tools:");
        $this->error("  • macOS: brew install postgresql");
        $this->error("  • Ubuntu/Debian: sudo apt-get install postgresql-client");
        $this->error("  • CentOS/RHEL: sudo yum install postgresql");
        return false;
      }
    } elseif ($connection === 'mysql') {
      $output = [];
      $returnCode = 0;
      exec('which mysqldump', $output, $returnCode);

      if ($returnCode !== 0) {
        $this->error("❌ MySQL tools not found!");
        $this->error("Please install MySQL client tools:");
        $this->error("  • macOS: brew install mysql-client");
        $this->error("  • Ubuntu/Debian: sudo apt-get install mysql-client");
        $this->error("  • CentOS/RHEL: sudo yum install mysql");
        return false;
      }
    }

    return true;
  }

  /**
   * Backup MySQL database using mysqldump.
   *
   * @param array $dbConfig
   * @param string $backupFile
   * @return void
   */
  private function backupMySQL($dbConfig, $backupFile)
  {
    // Handle DATABASE_URL if present
    if (!empty($dbConfig['url'])) {
      $parsedUrl = parse_url($dbConfig['url']);
      $host = $parsedUrl['host'];
      $port = $parsedUrl['port'] ?? 3306;
      $username = $parsedUrl['user'];
      $password = $parsedUrl['pass'];
      $database = ltrim($parsedUrl['path'], '/');
    } else {
      $host = $dbConfig['host'];
      $port = $dbConfig['port'];
      $username = $dbConfig['username'];
      $password = $dbConfig['password'];
      $database = $dbConfig['database'];
    }

    $command = sprintf(
      'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
      escapeshellarg($host),
      escapeshellarg($port),
      escapeshellarg($username),
      escapeshellarg($password),
      escapeshellarg($database),
      escapeshellarg($backupFile)
    );

    $this->executeBackup($command, $backupFile);
  }

  /**
   * Backup PostgreSQL database using pg_dump.
   *
   * @param array $dbConfig
   * @param string $backupFile
   * @return void
   */
  private function backupPostgreSQL($dbConfig, $backupFile)
  {
    // Handle DATABASE_URL if present
    if (!empty($dbConfig['url'])) {
      $parsedUrl = parse_url($dbConfig['url']);
      $host = $parsedUrl['host'];
      $port = $parsedUrl['port'] ?? 5432;
      $username = $parsedUrl['user'];
      $password = $parsedUrl['pass'];
      $database = ltrim($parsedUrl['path'], '/');
    } else {
      $host = $dbConfig['host'];
      $port = $dbConfig['port'];
      $username = $dbConfig['username'];
      $password = $dbConfig['password'];
      $database = $dbConfig['database'];
    }

    // Set PGPASSWORD environment variable for PostgreSQL
    $env = [
      'PGPASSWORD=' . escapeshellarg($password)
    ];

    // Try with standard options first
    $command = sprintf(
      'pg_dump --host=%s --port=%s --username=%s --dbname=%s --verbose --no-password --format=plain --no-sync --no-owner --no-privileges > %s',
      escapeshellarg($host),
      escapeshellarg($port),
      escapeshellarg($username),
      escapeshellarg($database),
      escapeshellarg($backupFile)
    );

    $this->executeBackup($command, $backupFile, $env);
  }

  /**
   * Execute the backup command.
   *
   * @param string $command
   * @param string $backupFile
   * @param array $env
   * @return void
   */
  private function executeBackup($command, $backupFile, $env = [])
  {
    $output = [];
    $returnCode = 0;

    // Set environment variables if provided
    $envString = '';
    if (!empty($env)) {
      $envString = implode(' ', $env) . ' ';
    }

    $fullCommand = $envString . $command;

    // Use proc_open to properly capture both stdout and stderr
    $descriptorspec = array(
      0 => array("pipe", "r"),  // stdin
      1 => array("pipe", "w"),  // stdout
      2 => array("pipe", "w")   // stderr
    );

    $process = proc_open($fullCommand, $descriptorspec, $pipes);

    if (is_resource($process)) {
      fclose($pipes[0]);
      $stdout = stream_get_contents($pipes[1]);
      $stderr = stream_get_contents($pipes[2]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      $returnCode = proc_close($process);

      $output = array_merge(
        $stdout ? explode("\n", trim($stdout)) : [],
        $stderr ? explode("\n", trim($stderr)) : []
      );
    } else {
      $output = ["Failed to start process"];
      $returnCode = 1;
    }

    if ($returnCode === 0) {
      if (file_exists($backupFile)) {
        $fileSize = $this->formatBytes(filesize($backupFile));
        $this->info("✅ Database backup completed successfully!");
        $this->info("📁 Backup file: {$backupFile}");
        $this->info("📊 File size: {$fileSize}");
      } else {
        $this->error("❌ Backup file was not created!");
      }
    } else {
      $this->error("❌ Database backup failed!");
      $this->error("Error code: {$returnCode}");
      $outputText = implode("\n", $output);
      $this->error("Output: " . $outputText);

      // Provide helpful information for common issues
      if (strpos($outputText, 'version mismatch') !== false) {
        $this->newLine();
        $this->warn("💡 Version mismatch detected!");
        $this->warn("Your local pg_dump version is older than the server version.");
        $this->warn("To fix this, update your PostgreSQL client tools:");
        $this->warn("  • macOS: brew upgrade postgresql");
        $this->warn("  • Or use: brew install postgresql@17");
        $this->newLine();
        $this->warn("Alternative: Use Supabase CLI for backups:");
        $this->warn("  • Install: npm install -g supabase");
        $this->warn("  • Login: supabase login");
        $this->warn("  • Backup: supabase db dump --project-ref YOUR_PROJECT_REF > backup.sql");
      }
    }
  }

  /**
   * Format bytes to a human-readable format.
   *
   * @param  int  $size
   * @param  int  $precision
   * @return string
   */
  private function formatBytes($size, $precision = 2)
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
      $size /= 1024;
    }

    return round($size, $precision) . ' ' . $units[$i];
  }

  /**
   * Show recent backup files in the specified directory.
   *
   * @param  string  $backupDir
   * @return void
   */
  private function showRecentBackups($backupDir)
  {
    $files = glob($backupDir . '/backup_*.sql');
    if (empty($files)) {
      return;
    }

    // Sort by modification time (newest first)
    usort($files, function ($a, $b) {
      return filemtime($b) - filemtime($a);
    });

    $this->newLine();
    $this->info("📋 Recent backups:");

    $recentFiles = array_slice($files, 0, 5);
    foreach ($recentFiles as $file) {
      $size = $this->formatBytes(filesize($file));
      $date = date('Y-m-d H:i:s', filemtime($file));
      $filename = basename($file);
      $this->line("  • {$filename} ({$size}) - {$date}");
    }
  }
}
