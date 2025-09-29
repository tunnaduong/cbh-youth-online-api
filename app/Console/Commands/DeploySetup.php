<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DeploySetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:setup
                            {--force : Force run without confirmation}
                            {--skip-migrate : Skip database migrations}
                            {--skip-cache : Skip cache operations}
                            {--skip-storage : Skip storage link creation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all necessary commands for production deployment setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting deployment setup...');
        $this->newLine();

        // Show confirmation unless --force is used
        if (!$this->option('force')) {
            if (!$this->confirm('This will run all deployment commands. Continue?')) {
                $this->info('Deployment setup cancelled.');
                return 0;
            }
        }

        $startTime = microtime(true);
        $success = true;

        try {
            // 1. Clear all caches
            if (!$this->option('skip-cache')) {
                $this->runCacheCommands();
            }

            // 2. Create storage link
            if (!$this->option('skip-storage')) {
                $this->createStorageLink();
            }

            // 3. Run database migrations
            if (!$this->option('skip-migrate')) {
                $this->runMigrations();
            }

            // 4. Run necessary commands
            $this->runNecessaryCommands();

            // 5. Create backup before final steps
            $this->createBackup();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info("✅ Deployment setup completed successfully in {$duration} seconds!");
            $this->info('🎉 Your application is ready for production!');

        } catch (\Exception $e) {
            $this->error("❌ Deployment setup failed: " . $e->getMessage());
            $success = false;
        }

        return $success ? 0 : 1;
    }

    /**
     * Run necessary commands
     */
    private function runNecessaryCommands()
    {
        $this->info('🔄 Running necessary commands...');
        $necessaryCommands = [
            'posts:convert-markdown',
            'recordings:convert-markdown',
            'topics:randomize-ids',
        ];

        foreach ($necessaryCommands as $command) {
            $this->line("  Running {$command}...");
            if ($command === 'topics:randomize-ids') {
                Artisan::call($command, ['--force' => true]);
            } else {
                Artisan::call($command);
            }
            $this->info("  ✅ {$command} completed");
        }
    }

    /**
     * Run cache clearing commands
     */
    private function runCacheCommands()
    {
        $this->info('🧹 Clearing caches...');

        $cacheCommands = [
            'config:clear' => 'Configuration cache',
            'route:clear' => 'Route cache',
            'view:clear' => 'View cache',
            'cache:clear' => 'Application cache',
            'event:clear' => 'Event cache',
        ];

        foreach ($cacheCommands as $command => $description) {
            $this->line("  Clearing {$description}...");
            Artisan::call($command);
            $this->info("  ✅ {$description} cleared");
        }

        $this->newLine();
    }

    /**
     * Create storage link
     */
    private function createStorageLink()
    {
        $this->info('🔗 Creating storage link...');

        try {
            Artisan::call('storage:link');
            $this->info('✅ Storage link created successfully');
        } catch (\Exception $e) {
            $this->warn("⚠️  Storage link creation failed: " . $e->getMessage());
            $this->warn('You may need to create it manually: php artisan storage:link');
        }

        $this->newLine();
    }

    /**
     * Run database migrations
     */
    private function runMigrations()
    {
        $this->info('🗄️  Running database migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Database migrations completed');
        } catch (\Exception $e) {
            $this->error("❌ Migration failed: " . $e->getMessage());
            throw $e;
        }

        $this->newLine();
    }

    /**
     * Create backup before final steps
     */
    private function createBackup()
    {
        $this->info('💾 Creating database backup...');

        try {
            Artisan::call('db:backup');
            $this->info('✅ Database backup created');
        } catch (\Exception $e) {
            $this->warn("⚠️  Backup creation failed: " . $e->getMessage());
            $this->warn('You may want to create a backup manually: php artisan db:backup');
        }

        $this->newLine();
    }

    /**
     * Show deployment summary
     */
    private function showDeploymentSummary()
    {
        $this->newLine();
        $this->info('📋 Deployment Summary:');
        $this->line('  • Caches cleared and optimized');
        $this->line('  • Storage link created');
        $this->line('  • Database migrated');
        $this->line('  • Backup created');
        $this->newLine();
        $this->info('🎯 Next steps:');
        $this->line('  1. Verify your .env configuration');
        $this->line('  2. Test your application');
        $this->line('  3. Configure your web server');
        $this->line('  4. Set up SSL certificate');
        $this->line('  5. Configure monitoring and logging');
    }
}
