<?php

namespace App\Console\Commands;

use App\Models\AuthAccount;
use App\Models\Topic;
use App\Models\TopicVote;
use App\Models\TopicComment;
use Illuminate\Console\Command;

class BoostUserRanking extends Command
{
    protected $signature = 'user:boost {username} {--posts=0} {--votes=0} {--comments=0}';
    protected $description = 'Boost user ranking by adding fake interactions';

    public function handle()
    {
        $username = $this->argument('username');
        $posts = (int) $this->option('posts');
        $votes = (int) $this->option('votes');
        $comments = (int) $this->option('comments');

        $user = AuthAccount::where('username', $username)->first();

        if (!$user) {
            $this->error("User '{$username}' not found!");
            return 1;
        }

        // Debug: Check user ID
        $userId = $user->id ?? $user->uid ?? null;
        if (!$userId) {
            $this->error("User ID is null for '{$username}'!");
            return 1;
        }

        // Add fake posts
        if ($posts > 0) {
            for ($i = 0; $i < $posts; $i++) {
                Topic::create([
                    'title' => "Boost Post #" . ($i + 1),
                    'description' => 'This is a ranking boost post.',
                    'user_id' => $userId,
                    'subforum_id' => 1, // Adjust based on your subforum structure
                    'created_at' => now()->subMinutes(rand(1, 1440)),
                ]);
            }
            $this->info("Added {$posts} fake posts for {$username}");
        }

        // Add fake votes to user's existing posts
        if ($votes > 0) {
            // Get existing user IDs to avoid foreign key constraint
            $existingUserIds = AuthAccount::pluck('id')->toArray();

            if (empty($existingUserIds)) {
                $this->error("No existing users found to create votes!");
                return 1;
            }

            $userPosts = Topic::where('user_id', $userId)->get();
            $votesAdded = 0;

            foreach ($userPosts as $post) {
                for ($i = 0; $i < min($votes, 10); $i++) { // Max 10 votes per post
                    $randomUserId = $existingUserIds[array_rand($existingUserIds)];

                    TopicVote::updateOrCreate([
                        'topic_id' => $post->id,
                        'user_id' => $randomUserId,
                    ], [
                        'vote_value' => 1,
                        'created_at' => now()->subMinutes(rand(1, 1440)),
                    ]);
                    $votesAdded++;
                }
            }
            $this->info("Added {$votesAdded} fake votes for {$username}'s posts");
        }

        // Add fake comments
        if ($comments > 0) {
            $randomPosts = Topic::inRandomOrder()->take(min($comments, 20))->get();
            foreach ($randomPosts as $post) {
                TopicComment::create([
                    'topic_id' => $post->id,
                    'user_id' => $userId,
                    'comment' => 'This is a ranking boost comment.',
                    'created_at' => now()->subMinutes(rand(1, 1440)),
                ]);
            }
            $this->info("Added {$comments} fake comments for {$username}");
        }

        return 0;
    }
}