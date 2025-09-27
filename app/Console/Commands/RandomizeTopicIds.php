<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RandomizeTopicIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'topics:randomize-ids {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Randomize all topic IDs in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will randomize ALL topic IDs. This action cannot be undone. Are you sure?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Starting to randomize topic IDs...');

        // Get all topics
        $topics = DB::table('cyo_topics')->get();
        $this->info("Found {$topics->count()} topics to randomize.");

        $progressBar = $this->output->createProgressBar($topics->count());
        $progressBar->start();

        $newIds = [];
        $idMapping = [];

        // First, generate all new randomized IDs
        foreach ($topics as $topic) {
            $newId = $this->generateRandomizedId($newIds);
            $newIds[] = $newId;
            $idMapping[$topic->id] = $newId;
        }

        $this->newLine();
        $this->info('Generated randomized IDs. Starting database updates...');

        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Update all related tables first
            $this->updateRelatedTables($idMapping);

            // Finally update the topics table
            $this->updateTopicsTable($idMapping);

            $this->newLine();
            $this->info('Successfully randomized all topic IDs!');
            $this->info('Sample mappings:');

            $sampleCount = min(5, count($idMapping));
            $sampleMappings = array_slice($idMapping, 0, $sampleCount, true);
            foreach ($sampleMappings as $oldId => $newId) {
                $this->line("ID {$oldId} -> {$newId}");
            }

        } catch (\Exception $e) {
            $this->error('Error occurred: ' . $e->getMessage());
            $this->error('Database may be in inconsistent state. Please check manually.');
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Generate a randomized ID
     */
    private function generateRandomizedId(array $existingIds): int
    {
        do {
            // Generate a random number between 100000 and 999999999
            $randomizedId = rand(100000, 999999999);
        } while (in_array($randomizedId, $existingIds));

        return $randomizedId;
    }

    /**
     * Update related tables that reference topic IDs
     */
    private function updateRelatedTables(array $idMapping)
    {
        $this->info('Updating related tables...');

        // Update topic_views table
        if (DB::getSchemaBuilder()->hasTable('cyo_topic_views')) {
            foreach ($idMapping as $oldId => $newId) {
                DB::table('cyo_topic_views')
                    ->where('topic_id', $oldId)
                    ->update(['topic_id' => $newId]);
            }
            $this->info('Updated cyo_topic_views table');
        }

        // Update topic_votes table
        if (DB::getSchemaBuilder()->hasTable('cyo_topic_votes')) {
            foreach ($idMapping as $oldId => $newId) {
                DB::table('cyo_topic_votes')
                    ->where('topic_id', $oldId)
                    ->update(['topic_id' => $newId]);
            }
            $this->info('Updated cyo_topic_votes table');
        }

        // Update topic_comments table
        if (DB::getSchemaBuilder()->hasTable('cyo_topic_comments')) {
            foreach ($idMapping as $oldId => $newId) {
                DB::table('cyo_topic_comments')
                    ->where('topic_id', $oldId)
                    ->update(['topic_id' => $newId]);
            }
            $this->info('Updated cyo_topic_comments table');
        }

        // Update user_saved_topics table
        if (DB::getSchemaBuilder()->hasTable('cyo_user_saved_topics')) {
            foreach ($idMapping as $oldId => $newId) {
                DB::table('cyo_user_saved_topics')
                    ->where('topic_id', $oldId)
                    ->update(['topic_id' => $newId]);
            }
            $this->info('Updated cyo_user_saved_topics table');
        }

        // Update user_reports table
        if (DB::getSchemaBuilder()->hasTable('cyo_user_reports')) {
            foreach ($idMapping as $oldId => $newId) {
                DB::table('cyo_user_reports')
                    ->where('topic_id', $oldId)
                    ->update(['topic_id' => $newId]);
            }
            $this->info('Updated cyo_user_reports table');
        }
    }

    /**
     * Update the main topics table
     */
    private function updateTopicsTable(array $idMapping)
    {
        $this->info('Updating topics table...');

        // Create a temporary table
        DB::statement('CREATE TABLE cyo_topics_temp LIKE cyo_topics');

        // Copy data with new randomized IDs
        foreach ($idMapping as $oldId => $newId) {
            $topic = DB::table('cyo_topics')->where('id', $oldId)->first();
            if ($topic) {
                $topicData = (array) $topic;
                $topicData['id'] = $newId;
                DB::table('cyo_topics_temp')->insert($topicData);
            }
        }

        // Drop original table and rename temp table
        DB::statement('DROP TABLE cyo_topics');
        DB::statement('RENAME TABLE cyo_topics_temp TO cyo_topics');

        $this->info('Updated cyo_topics table with randomized IDs');
    }
}
