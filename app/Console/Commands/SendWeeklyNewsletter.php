<?php

namespace App\Console\Commands;

use App\Mail\WeeklyNewsletterMail;
use App\Models\AuthAccount;
use App\Models\NotificationSettings;
use App\Models\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'newsletter:send-weekly
        {--user= : Send only to one account ID}
        {--limit= : Maximum number of recipients}
        {--dry-run : Display recipients and articles without sending email}';

    protected $description = 'Send the weekly forum newsletter';

    public function handle(): int
    {
        $now = now();
        $newTopicsSince = $now->copy()->subWeek();
        $hotTopics = $this->hotTopics($now->copy()->subDays(30));
        $sent = 0;

        $recipients = AuthAccount::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereIn('id', NotificationSettings::query()
                ->where('notify_email_marketing', true)
                ->select('user_id'))
            ->when($this->option('user'), fn ($query, $userId) => $query->whereKey($userId))
            ->orderBy('id');

        $processAccounts = function ($accounts) use ($newTopicsSince, $hotTopics, &$sent) {
            foreach ($accounts as $account) {
                $newTopics = Topic::query()
                    ->with('user')
                    ->withCount('comments')
                    ->withSum('votes', 'vote_value')
                    ->where('hidden', 0)
                    ->where('created_at', '>=', $newTopicsSince)
                    ->whereDoesntHave('views', fn ($query) => $query->where('user_id', $account->id))
                    ->latest()
                    ->limit(5)
                    ->get();

                $topics = $newTopics->concat($hotTopics)
                    ->unique('id')
                    ->take(10)
                    ->map(fn (Topic $topic) => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'excerpt' => \Illuminate\Support\Str::limit(
                            strip_tags($topic->content_html ?: $topic->description),
                            220,
                        ),
                        'vote_score' => (int) ($topic->votes_sum_vote_value ?? 0),
                        'comments_count' => (int) ($topic->getRawOriginal('comments_count') ?? 0),
                        'author_username' => $topic->user?->username ?: 'user',
                    ])
                    ->values()
                    ->all();

                if (empty($topics)) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("{$account->email}: " . count($topics) . ' bài viết');
                } else {
                    Mail::to($account->email)->queue(new WeeklyNewsletterMail($account, $topics));
                }

                $sent++;
            }
        };

        if ($this->option('limit')) {
            $recipients->limit((int) $this->option('limit'))
                ->get()
                ->chunk(100)
                ->each($processAccounts);
        } else {
            $recipients->chunkById(100, $processAccounts);
        }

        $this->info(($this->option('dry-run') ? 'Prepared ' : 'Queued ') . "{$sent} weekly newsletter(s).");

        return self::SUCCESS;
    }

    protected function hotTopics(Carbon $since)
    {
        return Topic::query()
            ->with('user')
            ->withCount('comments')
            ->withSum('votes', 'vote_value')
            ->where('hidden', 0)
            ->where('created_at', '>=', $since)
            ->get()
            ->sortByDesc(fn (Topic $topic) => (int) ($topic->votes_sum_vote_value ?? 0) + ((int) ($topic->comments_count ?? 0) * 2))
            ->take(5)
            ->values();
    }
}