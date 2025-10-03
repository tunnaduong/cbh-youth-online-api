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
    protected $description = 'Create a backup of the database';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        // Get database configuration
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Create backup directory if it doesn't exist
        $backupDir = $this->option('path') ?: storage_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Generate backup filename with timestamp
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/backup_' . $database . '_' . $timestamp . '.sql';

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupFile)
        );

        $this->info("Creating backup: {$backupFile}");

        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $fileSize = $this->formatBytes(filesize($backupFile));
            $this->info("✅ Database backup completed successfully!");
            $this->info("📁 Backup file: {$backupFile}");
            $this->info("📊 File size: {$fileSize}");

            // Show recent backups
            $this->showRecentBackups($backupDir);
        } else {
            $this->error("❌ Database backup failed!");
            $this->error("Error code: {$returnCode}");
            $this->error("Output: " . implode("\n", $output));
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
