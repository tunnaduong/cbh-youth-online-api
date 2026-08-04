<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageCompression;
use App\Models\Topic;
use App\Models\TopicComment;
use App\Models\TopicCommentVote;
use App\Models\TopicView;
use App\Models\TopicVote;
use App\Models\UserContent;
use App\Models\UserSavedTopic;
use App\Services\HashtagService;
use App\Models\AuthAccount;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\CommonMarkConverter;

/**
 * Handles all API-related actions for topics, including creation, retrieval, voting, and commenting.
 */
class TopicsController extends Controller
{
  /**
   * Convert markdown to HTML using CommonMark.
   *
   * @param  string  $markdown
   * @return string
   */
  private function convertMarkdownToHtml(string $markdown): string
  {
    // 1. Chặn toàn bộ HTML thô
    $config = [
      'html_input' => 'strip',  // loại bỏ tất cả HTML trong Markdown
      'allow_unsafe_links' => false,  // chặn javascript: links
      'renderer' => [
        'soft_break' => "<br>\n",
      ],
    ];

    $converter = new CommonMarkConverter($config);
    $converter->getEnvironment()->addExtension(new AutolinkExtension());

    // 2. Chuyển Markdown → HTML
    $html = $converter->convert($markdown)->getContent();

    // 3. Thêm iframe theo whitelist
    // ví dụ chỉ cho phép YouTube/Vimeo
    preg_match_all('#<iframe[^>]+src="([^"]+)"[^>]*>.*?</iframe>#is', $markdown, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
      $src = $m[1];
      if (preg_match('#^(https?:)?//(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#', $src)) {
        // giữ iframe, append vào cuối HTML
        $html .= "\n" . $m[0];
      }
    }

    return $html;
  }

  /**
   * Get a paginated list of topics.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $query = $this->visibleTopicsQuery(auth()->id())
      ->select([
        'id',
        'subforum_id',
        'user_id',
        'title',
        'content_html',
        'created_at',
        'updated_at',
        'privacy',
        'hidden',
        'pinned',
        'anonymous',
        'cdn_image_id',
        'cdn_document_id',
        'cdn_video_id',
        'deleted_at'
      ])
      ->withCount(['views', 'comments'])
      ->orderBy('created_at', 'desc')
      ->with(['user', 'votes.user', 'cdnUserContent']);

    $topics = $query
      ->paginate(10)  // Paginate with 10 items per page
      ->through(fn($topic) => $this->formatTopicForList($topic, $request));

    // Return the paginated topics as a JSON response
    return response()->json($topics);
  }

  /**
   * Build the base query of topics a user (or guest, if null) is allowed to see:
   * not hidden, not by a blocked user, and public / own / followers-only-from-followed.
   * Shared by index(), the personalized feed, the "latest" fallback, and the
   * new-posts refresh check so the visibility rules never drift apart.
   *
   * @param  int|null  $userId
   * @return \Illuminate\Database\Eloquent\Builder
   */
  private function visibleTopicsQuery(?int $userId)
  {
    $query = Topic::where('hidden', 0);

    if (!$userId) {
      return $query->where('privacy', 'public')->where('hidden', 0);
    }

    $followingIds = \App\Models\Follower::where('follower_id', $userId)
      ->pluck('followed_id')
      ->toArray();

    $blockedUserIds = \App\Models\UserBlock::where('user_id', $userId)
      ->pluck('blocked_user_id')
      ->toArray();

    $query->whereNotIn('user_id', $blockedUserIds);

    $query->where(function ($q) use ($userId, $followingIds) {
      $q
        ->where(function ($subQ) {
          $subQ->where('privacy', 'public')->where('hidden', 0);
        })
        ->orWhere('user_id', $userId)
        ->orWhere(function ($subQ) use ($followingIds) {
          $subQ
            ->where('privacy', 'followers')
            ->where('hidden', 0)
            ->whereIn('user_id', $followingIds);
        });
    });

    return $query;
  }

  /**
   * Format a topic model into the array shape used by the feed list endpoints.
   *
   * @param  \App\Models\Topic  $topic
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  /**
   * Resolve @mentions in a topic's text to {username, user_id} pairs for
   * client-side rendering. "all" is never resolvable (no account can have
   * that username), so it's naturally excluded here already.
   *
   * @param  string|null  $text
   * @return array<array{username:string,user_id:int}>
   */
  private function resolveTopicMentions(?string $text): array
  {
    if (!$text) {
      return [];
    }

    $usernames = NotificationService::parseMentions($text);
    if (empty($usernames)) {
      return [];
    }

    return AuthAccount::whereIn('username', $usernames)
      ->select('id', 'username')
      ->get()
      ->map(fn($u) => ['username' => $u->username, 'user_id' => $u->id])
      ->all();
  }

  private function formatTopicForList(Topic $topic, Request $request): array
  {
    // Check if user is moderator/admin (you may need to adjust this logic based on your role system)
    $isModerator = $request->user() && (
      $request->user()->hasRole('admin') ||
      $request->user()->hasRole('moderator') ||
      $request->user()->id === 1  // Assuming user ID 1 is admin, adjust as needed
    );

    $topicData = [
      'id' => $topic->id,
      'title' => $topic->title,
      'content' => $topic->content_html,
      'mentions' => $this->resolveTopicMentions($topic->description),
      'image_urls' => $topic->getImageUrls()->map(function ($content) {
        return config('app.url') . Storage::url($content->file_path);
      })->all(),
      'document_urls' => $topic->getDocuments()->map(function ($content) {
        return config('app.url') . Storage::url($content->file_path);
      })->all(),
      'document_sizes' => $topic->getDocuments()->map(function ($content) {
        return $content->file_size;
      })->all(),
      'video_urls' => $topic->getVideos()->map(function ($content) {
        return config('app.url') . Storage::url($content->file_path);
      })->all(),
      'author' => $topic->anonymous && !$isModerator ? [
        'id' => null,
        'username' => 'Ẩn danh',
        'email' => null,
        'profile_name' => 'Người dùng ẩn danh',
        'verified' => false,
      ] : [
        'id' => $topic->user->id,
        'username' => $topic->user->username,
        'email' => $topic->user->email,
        'profile_name' => $topic->user->profile->profile_name ?? null,
        'verified' => $topic->user->profile->verified == 1 ?? false ? true : false,
      ],
      'anonymous' => $topic->anonymous,
      'is_edited' => $topic->is_edited,
      'is_muted' => $topic->is_muted,
      'time' => Carbon::parse($topic->created_at)->diffForHumans(),  // Time in human-readable format
      'comments' => $topic->comments_count,  // Comment count in '05+' format (already formatted by accessor)
      'views' => is_numeric($topic->views_count) ? (int) $topic->views_count : 0,  // Ensure numeric value
      'votes' => $topic->votes->map(function ($vote) {
        return [
          'username' => $vote->user->username,  // Assuming votes relation includes the user
          'vote_value' => $vote->vote_value,
          'created_at' => $vote->created_at ? $vote->created_at->toISOString() : null,
          'updated_at' => $vote->updated_at ? $vote->updated_at->toISOString() : null,
        ];
      }),
    ];

    // Check if the user is authenticated
    $isOwner = false;
    if ($request->user()) {
      $topicData['saved'] = $topic->isSavedByUser($request->user()->id);
      $isOwner = $request->user()->id === $topic->user_id;
    } else {
      $topicData['saved'] = false;
    }

    $topicData['is_owner'] = $isOwner;

    return $topicData;
  }

  /**
   * Get a personalized, scored newsfeed for the authenticated user.
   * Falls back to the plain reverse-chronological feed for guests.
   *
   * Scoring = (W_affinity * affinity + W_engage * engagement) * time_decay,
   * with pinned topics always floated to the top and same-author
   * results de-duplicated so they don't appear back-to-back.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function feed(Request $request)
  {
    $userId = auth()->id();

    if (!$userId) {
      return $this->index($request);
    }

    $perPage = 10;
    $page = max(1, (int) $request->query('page', 1));

    if ($request->query('mode') === 'latest') {
      return $this->latestFeed($request, $userId, $page, $perPage);
    }

    if ($request->query('mode') === 'following') {
      return $this->followingFeed($request, $userId, $page, $perPage);
    }

    // Optional per-visit shuffle seed ("slot machine"): the client generates a new
    // random seed on every page load, so the same ranked list is re-dealt into a
    // different order each visit while one seed keeps pagination stable within a visit.
    $seed = $request->query('seed');
    $seed = is_numeric($seed) ? ((int) abs($seed) % 2147483647) : null;

    // Redis temporarily disabled — using the default cache driver instead.
    // Switch back to Cache::store('redis') once Redis is set up.
    // Cache key includes the global feed version so it's invalidated for everyone
    // as soon as a new topic is created (see bumpFeedVersion()), instead of only
    // expiring after the 30-minute TTL.
    $feedVersion = Cache::get('feed_version', 1);
    $cacheKey = "feed_scores_v3_user_{$userId}_v{$feedVersion}";  // v3: caches the ranked list, shuffling happens per request
    $ranked = Cache::remember(
      $cacheKey,
      now()->addMinutes(30),
      fn() => $this->buildPersonalizedFeedOrder($userId)
    );

    $total = count($ranked);
    $orderedIds = $this->arrangeFeedOrder($ranked, $seed, $page * $perPage);
    $pageIds = array_slice($orderedIds, ($page - 1) * $perPage, $perPage);

    $formatted = collect();

    if (!empty($pageIds)) {
      $topicsById = Topic::whereIn('id', $pageIds)
        ->withCount(['views', 'comments'])
        ->with(['user.profile', 'votes.user', 'cdnUserContent'])
        ->get()
        ->keyBy('id');

      $formatted = collect($pageIds)
        ->map(fn($id) => $topicsById->get($id))
        ->filter()
        ->map(fn($topic) => $this->formatTopicForList($topic, $request))
        ->values();
    }

    $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
      $formatted,
      $total,
      $perPage,
      $page,
      ['path' => $request->url(), 'query' => $request->query()]
    );

    $exhausted = ($page * $perPage) >= $total;

    return response()->json(array_merge($paginator->toArray(), ['exhausted' => $exhausted]));
  }

  /**
   * Chronological fallback feed, used once the client has paginated through the
   * entire personalized ranked list. Independently paginated from the personalized
   * feed (its own `page` counter) — the client is expected to dedupe against IDs
   * it has already rendered.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $userId
   * @param  int  $page
   * @param  int  $perPage
   * @return \Illuminate\Http\JsonResponse
   */
  private function latestFeed(Request $request, int $userId, int $page, int $perPage)
  {
    $topics = $this->visibleTopicsQuery($userId)
      ->withCount(['views', 'comments'])
      ->orderBy('created_at', 'desc')
      ->with(['user', 'votes.user', 'cdnUserContent'])
      ->paginate($perPage, ['*'], 'page', $page)
      ->through(fn($topic) => $this->formatTopicForList($topic, $request));

    return response()->json(array_merge($topics->toArray(), ['exhausted' => false, 'mode' => 'latest']));
  }

  /**
   * Reverse-chronological feed restricted to people the authenticated user follows.
   */
  private function followingFeed(Request $request, int $userId, int $page, int $perPage)
  {
    $followingIds = \App\Models\Follower::where('follower_id', $userId)
      ->pluck('followed_id')
      ->toArray();

    if (empty($followingIds)) {
      return response()->json([
        'data' => [],
        'total' => 0,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => 1,
        'exhausted' => true,
        'mode' => 'following',
      ]);
    }

    $blockedUserIds = \App\Models\UserBlock::where('user_id', $userId)
      ->pluck('blocked_user_id')
      ->toArray();

    $topics = Topic::where('hidden', 0)
      ->whereIn('user_id', $followingIds)
      ->whereNotIn('user_id', $blockedUserIds)
      ->where(function ($q) use ($userId, $followingIds) {
        $q->where('privacy', 'public')
          ->orWhere('user_id', $userId)
          ->orWhere(function ($sub) use ($followingIds) {
            $sub->where('privacy', 'followers')->whereIn('user_id', $followingIds);
          });
      })
      ->withCount(['views', 'comments'])
      ->with(['user', 'votes.user', 'cdnUserContent'])
      ->orderBy('created_at', 'desc')
      ->paginate($perPage, ['*'], 'page', $page)
      ->through(fn($topic) => $this->formatTopicForList($topic, $request));

    return response()->json(array_merge($topics->toArray(), ['exhausted' => false, 'mode' => 'following']));
  }

  /**
   * Invalidate every user's cached personalized feed order by bumping the
   * global feed version used in the feed() cache key. Called whenever a new
   * topic is created so it shows up immediately instead of waiting out the
   * 30-minute feed_scores_v2 cache TTL.
   */
  private function bumpFeedVersion(): void
  {
    if (!Cache::has('feed_version')) {
      Cache::forever('feed_version', 1);
    }
    Cache::increment('feed_version');
  }

  /**
   * Build the ranked list of topics for a user's personalized feed, best score first.
   *
   * Candidate pool: every topic the user is allowed to see. Affinity is derived from follows and
   * from the user's historical voting/commenting behavior toward authors
   * and subforums (there is no separate "follow a subforum" feature yet).
   *
   * The result is cached as-is; the final ordering (author de-duplication and the
   * optional per-visit shuffle) is applied per request by arrangeFeedOrder().
   *
   * @param  int  $userId
   * @return array<array{id:int,user_id:int,pinned:bool}>
   */
  private function buildPersonalizedFeedOrder(int $userId): array
  {
    // Limit the candidate pool to the last 90 days so the query stays fast
    // regardless of total post count. Pinned posts are included unconditionally.
    $cutoff = now()->subDays(90);

    $candidates = $this->visibleTopicsQuery($userId)
      ->select(['id', 'user_id', 'subforum_id', 'pinned', 'created_at'])
      ->withSum('votes', 'vote_value')
      ->withCount(['comments as comments_total'])
      ->where(function ($q) use ($cutoff) {
        $q->where('created_at', '>=', $cutoff)->orWhere('pinned', 1);
      })
      ->orderBy('created_at', 'desc')
      ->limit(500)
      ->get();

    if ($candidates->isEmpty()) {
      return [];
    }

    return collect($this->scoreTopics($candidates, $userId))
      ->sortByDesc('score')
      ->map(fn($item) => ['id' => $item['id'], 'user_id' => $item['user_id'], 'pinned' => $item['pinned']])
      ->values()
      ->all();
  }

  /**
   * Score a collection of candidate topics for a user's personalized feed.
   * Candidates must already have `votes_sum_vote_value` and `comments_total`
   * loaded (via withSum('votes','vote_value') / withCount(['comments as comments_total'])).
   *
   * Affinity signals: how much the user has historically engaged with each author / subforum.
   * (No dedicated "follow a subforum" table exists yet, so subforum affinity is inferred from history.)
   *
   * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $candidates
   * @param  int  $userId
   * @return array<array{id:int,user_id:int,score:float}>
   */
  private function scoreTopics($candidates, int $userId): array
  {
    $followingIds = \App\Models\Follower::where('follower_id', $userId)->pluck('followed_id')->toArray();

    $authorVoteAffinity = DB::table('cyo_topic_votes')
      ->join('cyo_topics', 'cyo_topic_votes.topic_id', '=', 'cyo_topics.id')
      ->where('cyo_topic_votes.user_id', $userId)
      ->selectRaw('cyo_topics.user_id as author_id, COUNT(*) as cnt')
      ->groupBy('cyo_topics.user_id')
      ->pluck('cnt', 'author_id');

    $authorCommentAffinity = DB::table('cyo_topic_comments')
      ->join('cyo_topics', 'cyo_topic_comments.topic_id', '=', 'cyo_topics.id')
      ->where('cyo_topic_comments.user_id', $userId)
      ->selectRaw('cyo_topics.user_id as author_id, COUNT(*) as cnt')
      ->groupBy('cyo_topics.user_id')
      ->pluck('cnt', 'author_id');

    $subforumAffinity = DB::table('cyo_topic_votes')
      ->join('cyo_topics', 'cyo_topic_votes.topic_id', '=', 'cyo_topics.id')
      ->where('cyo_topic_votes.user_id', $userId)
      ->whereNotNull('cyo_topics.subforum_id')
      ->selectRaw('cyo_topics.subforum_id, COUNT(*) as cnt')
      ->groupBy('cyo_topics.subforum_id')
      ->pluck('cnt', 'subforum_id');

    $wAffinity = 2.0;
    $wEngage = 1.0;
    $gamma = 1.6;  // time-decay exponent: higher = older posts drop off faster

    return $candidates->map(function ($topic) use (
      $followingIds,
      $authorVoteAffinity,
      $authorCommentAffinity,
      $subforumAffinity,
      $wAffinity,
      $wEngage,
      $gamma
    ) {
      $affinity = 0;
      $affinity += in_array($topic->user_id, $followingIds) ? 5 : 0;
      $affinity += min(10, $authorVoteAffinity[$topic->user_id] ?? 0) * 1.5;
      $affinity += min(10, $authorCommentAffinity[$topic->user_id] ?? 0) * 2;
      $affinity += min(10, $subforumAffinity[$topic->subforum_id] ?? 0) * 0.5;

      $engagement = ($topic->votes_sum_vote_value ?? 0) * 1 + ($topic->comments_total ?? 0) * 3;

      $hoursOld = max(0, now()->diffInHours($topic->created_at));
      $decay = 1 / pow($hoursOld + 2, $gamma);

      $score = ($wAffinity * $affinity + $wEngage * $engagement) * $decay;

      // Pinned posts always float to the top regardless of score.
      if ($topic->pinned) {
        $score += 1_000_000;
      }

      return [
        'id' => $topic->id,
        'user_id' => $topic->user_id,
        'pinned' => (bool) $topic->pinned,
        'score' => $score,
      ];
    })->all();
  }

  /**
   * Turn the scored ranking into the IDs actually served, up to $limit items.
   *
   * Walks the ranking front to back and, for each slot, looks at a small window of
   * the best remaining candidates:
   *   - pinned topics are taken straight away, so they always lead the feed;
   *   - candidates by the previous slot's author are skipped, so the same person
   *     never appears twice in a row;
   *   - with a $seed, the pick inside the window is randomized with an
   *     exponentially decaying weight (best candidate most likely), which is what
   *     makes a reload deal a visibly different feed while keeping the ranking's
   *     bias intact. Without a seed the best eligible candidate always wins, i.e.
   *     the plain deterministic order.
   *
   * Deterministic for a given ($ranked, $seed) pair, so page N+1 continues exactly
   * where page N stopped as long as the client keeps sending the same seed.
   *
   * @param  array<array{id:int,user_id:int,pinned:bool}>  $ranked
   * @param  int|null  $seed
   * @param  int  $limit
   * @return array<int>
   */
  private function arrangeFeedOrder(array $ranked, ?int $seed, int $limit): array
  {
    $count = count($ranked);

    if ($count === 0 || $limit <= 0) {
      return [];
    }

    $window = 18;  // how far down the ranking a single slot may reach
    $decay = 0.85;  // weight ratio between consecutive candidates in the window

    // Small self-contained LCG: the shuffle depends only on the seed and never
    // touches (nor is disturbed by) PHP's global mt_rand state.
    $state = $seed === null ? 0 : max(1, $seed);
    $random = function () use (&$state) {
      $state = ($state * 1103515245 + 12345) % 2147483648;
      return $state / 2147483648;
    };

    $used = [];
    $result = [];
    $cursor = 0;
    $lastAuthor = null;

    while (count($result) < $limit) {
      while ($cursor < $count && isset($used[$cursor])) {
        $cursor++;
      }

      if ($cursor >= $count) {
        break;
      }

      $candidates = [];
      for ($i = $cursor; $i < $count && count($candidates) < $window; $i++) {
        if (!isset($used[$i])) {
          $candidates[] = $i;
        }
      }

      if ($ranked[$candidates[0]]['pinned']) {
        $picked = $candidates[0];
      } else {
        $eligible = array_values(array_filter(
          $candidates,
          fn($i) => $ranked[$i]['user_id'] !== $lastAuthor
        ));

        // Everything in the window is by the last author; take the best one anyway.
        if (empty($eligible)) {
          $eligible = $candidates;
        }

        $picked = $seed === null ? $eligible[0] : $this->weightedPick($eligible, $decay, $random);
      }

      $used[$picked] = true;
      $result[] = $ranked[$picked]['id'];
      $lastAuthor = $ranked[$picked]['user_id'];
    }

    return $result;
  }

  /**
   * Pick one entry from an ordered candidate list, weighting position $i by
   * $decay ** $i so earlier (better ranked) candidates win more often.
   *
   * @param  array<int>  $candidates  ranked-list indexes, best first
   * @param  float  $decay
   * @param  callable(): float  $random  returns a float in [0, 1)
   * @return int
   */
  private function weightedPick(array $candidates, float $decay, callable $random): int
  {
    $weights = [];
    $sum = 0.0;

    foreach ($candidates as $slot => $_) {
      $weight = pow($decay, $slot);
      $weights[$slot] = $weight;
      $sum += $weight;
    }

    $roll = $random() * $sum;

    foreach ($weights as $slot => $weight) {
      $roll -= $weight;
      if ($roll <= 0) {
        return $candidates[$slot];
      }
    }

    return $candidates[array_key_last($candidates)];
  }

  /**
   * Round a number down to the nearest multiple of five.
   *
   * @param  int  $count
   * @return string
   */
  private function roundToNearestFive($count)
  {
    // Ensure count is numeric, default to 0 if not
    $count = is_numeric($count) ? (int) $count : 0;

    if ($count <= 5) {
      // If count is less than or equal to 5, format it with leading zero
      return str_pad($count, 2, '0', STR_PAD_LEFT);
    } else {
      // Round down to the nearest multiple of 5 and pad to 2 digits
      return str_pad(floor($count / 5) * 5, 2, '0', STR_PAD_LEFT);
    }
  }

  /**
   * Display the specified topic.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show(Request $request, $id)
  {
    // Find the topic with related data or return a 404 error if not found
    $topic = Topic::with([
      'author.profile',
      'comments.user.profile',
      'user',
      'votes.user',
      'cdnUserContent'
    ])
      ->withCount(['comments as reply_count', 'views'])
      ->find($id);

    if (!$topic) {
      return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);  // Not Found
    }

    // Check privacy settings
    if ($topic->privacy === 'private') {
      // Only the author can see private posts (privacy = private)
      if (!auth()->check() || $topic->user_id !== auth()->id()) {
        return response()->json(['message' => 'Bạn không có quyền xem bài viết này'], 403);
      }
    } elseif ($topic->privacy === 'followers') {
      // Only followers can see followers-only posts
      if (!auth()->check()) {
        return response()->json(['message' => 'Bạn cần đăng nhập để xem bài viết này'], 403);
      }

      if ($topic->user_id !== auth()->id()) {
        $isFollowing = \App\Models\Follower::where('follower_id', auth()->id())
          ->where('followed_id', $topic->user_id)
          ->exists();

        if (!$isFollowing) {
          return response()->json(['message' => 'Bạn cần theo dõi tác giả để xem bài viết này'], 403);
        }
      }
    }

    // Load comments with their respective votes and voter usernames
    // Need to load nested replies recursively with all levels
    $comments = $topic
      ->comments()
      ->whereNull('replying_to')
      ->with([
        'user.profile',
        'votes.user',
        'replies' => function ($q) {
          $q->select('*');  // Ensure all columns including deleted_parent_username are loaded
          $q->with([
            'user.profile',
            'votes.user',
            'replies' => function ($subQ) {
              $subQ->select('*');  // Ensure all columns including deleted_parent_username are loaded
              $subQ->with([
                'user.profile',
                'votes.user',
              ]);
            },
          ]);
        }
      ])
      ->orderBy('created_at', 'desc')
      ->get();

    // Batch-resolve @mentions across all comments/replies in one DB query
    $allCommentTexts = [];
    foreach ($comments as $c) {
      if ($c->comment) $allCommentTexts[] = $c->comment;
      foreach ($c->replies as $r) {
        if ($r->comment) $allCommentTexts[] = $r->comment;
        foreach ($r->replies as $sr) {
          if ($sr->comment) $allCommentTexts[] = $sr->comment;
        }
      }
    }
    $allCommentUsernames = empty($allCommentTexts) ? [] : array_unique(array_merge(
      ...array_map(fn($t) => \App\Services\NotificationService::parseMentions($t), $allCommentTexts)
    ));
    $resolvedCommentUsers = [];
    if (!empty($allCommentUsernames)) {
      foreach (\App\Models\AuthAccount::whereIn('username', $allCommentUsernames)->select('id', 'username')->get() as $u) {
        $resolvedCommentUsers[strtolower($u->username)] = ['username' => $u->username, 'user_id' => $u->id];
      }
    }

    $resolveMentionsFromText = function (string $text) use ($resolvedCommentUsers): array {
      $result = [];
      foreach (\App\Services\NotificationService::parseMentions($text) as $un) {
        $key = strtolower($un);
        if (isset($resolvedCommentUsers[$key])) {
          $result[] = $resolvedCommentUsers[$key];
        }
      }
      return $result;
    };

    $formattedComments = $comments->map(function ($comment) use ($resolveMentionsFromText) {
      return [
        'id' => $comment->id,
        'content' => $comment->comment,
        'comment' => $comment->comment_html,
        'is_anonymous' => $comment->is_anonymous,
        'deleted_parent_username' => $comment->deleted_parent_username ?? null,
        'author' => [
          'id' => $comment->user->id,
          'username' => $comment->user->username,
          'email' => $comment->user->email,
          'profile_name' => $comment->user->profile->profile_name ?? null,
          'verified' => $comment->user->profile->verified == 1 ?? false ? true : false,
        ],
        'created_at' => $comment->created_at->diffForHumans(),
        'updated_at' => $comment->updated_at ? $comment->updated_at->diffForHumans() : null,
        'is_edited' => $comment->is_edited,
        'image_urls' => $comment->image_urls
          ? array_map(fn($p) => config('app.url') . Storage::url($p), $comment->image_urls)
          : [],
        'votes' => $comment->votes->map(fn($vote) => [
          'user_id' => $vote->user_id,
          'username' => $vote->user->username,
          'vote_value' => $vote->vote_value,
        ]),
        'mentions' => $comment->comment ? $resolveMentionsFromText($comment->comment) : [],
        'replies' => $comment->replies->map(function ($reply) use ($resolveMentionsFromText) {
          // Sort level-3 replies so that a reply targeting a sibling appears right
          // after that sibling (sandwich ordering). Replies without a target_comment_id
          // keep their natural chronological position.
          $subReplies = $reply->replies->sortBy('created_at')->values();
          $ordered = collect();
          $inserted = [];
          foreach ($subReplies as $sr) {
            if (isset($inserted[$sr->id])) continue;
            $ordered->push($sr);
            $inserted[$sr->id] = true;
            // After inserting this sub-reply, insert any others that target it
            foreach ($subReplies as $child) {
              if (!isset($inserted[$child->id]) && $child->target_comment_id === $sr->id) {
                $ordered->push($child);
                $inserted[$child->id] = true;
              }
            }
          }

          return [
            'id' => $reply->id,
            'content' => $reply->comment,
            'comment' => $reply->comment_html,
            'is_anonymous' => $reply->is_anonymous,
            'deleted_parent_username' => $reply->getAttribute('deleted_parent_username') ?? null,
            'author' => [
              'id' => $reply->user->id,
              'username' => $reply->user->username,
              'email' => $reply->user->email,
              'profile_name' => $reply->user->profile->profile_name ?? null,
              'verified' => $reply->user->profile->verified == 1 ?? false ? true : false,
            ],
            'created_at' => $reply->created_at->diffForHumans(),
            'updated_at' => $reply->updated_at ? $reply->updated_at->diffForHumans() : null,
            'is_edited' => $reply->is_edited,
            'image_urls' => $reply->image_urls
              ? array_map(fn($p) => config('app.url') . Storage::url($p), $reply->image_urls)
              : [],
            'votes' => $reply->votes->map(fn($vote) => [
              'user_id' => $vote->user_id,
              'username' => $vote->user->username,
              'vote_value' => $vote->vote_value,
            ]),
            'mentions' => $reply->comment ? $resolveMentionsFromText($reply->comment) : [],
            'replies' => $ordered->map(function ($subReply) use ($resolveMentionsFromText, $subReplies) {
              // Resolve target author from siblings so frontend can show "→ @user"
              $targetAuthor = null;
              if ($subReply->target_comment_id) {
                $targetSibling = $subReplies->firstWhere('id', $subReply->target_comment_id);
                if ($targetSibling) {
                  $targetAuthor = $targetSibling->is_anonymous ? null : [
                    'username' => $targetSibling->user->username,
                    'profile_name' => $targetSibling->user->profile->profile_name ?? null,
                  ];
                }
              }
              return [
                'id' => $subReply->id,
                'content' => $subReply->comment,
                'comment' => $subReply->comment_html,
                'is_anonymous' => $subReply->is_anonymous,
                'deleted_parent_username' => $subReply->getAttribute('deleted_parent_username') ?? null,
                'target_comment_id' => $subReply->target_comment_id,
                'target_author' => $targetAuthor,
                'author' => [
                  'id' => $subReply->user->id,
                  'username' => $subReply->user->username,
                  'email' => $subReply->user->email,
                  'profile_name' => $subReply->user->profile->profile_name ?? null,
                  'verified' => $subReply->user->profile->verified == 1 ?? false ? true : false,
                ],
                'created_at' => $subReply->created_at->diffForHumans(),
                'updated_at' => $subReply->updated_at ? $subReply->updated_at->diffForHumans() : null,
                'is_edited' => $subReply->is_edited,
                'image_urls' => $subReply->image_urls
                  ? array_map(fn($p) => config('app.url') . Storage::url($p), $subReply->image_urls)
                  : [],
                'votes' => $subReply->votes->map(fn($vote) => [
                  'user_id' => $vote->user_id,
                  'username' => $vote->user->username,
                  'vote_value' => $vote->vote_value,
                ]),
                'mentions' => $subReply->comment ? $resolveMentionsFromText($subReply->comment) : [],
              ];
            })->values(),
          ];
        }),
      ];
    });

    // Get the first image URL for og:image
    $imageUrls = $topic->getImageUrls()->map(function ($content) {
      return config('app.url') . Storage::url($content->file_path);
    })->all();

    $ogImage = !empty($imageUrls) ? $imageUrls[0] : asset('images/cyo_thumbnail.png');

    $isSaved = false;
    if (auth()->check()) {
      $isSaved = UserSavedTopic::where('user_id', auth()->id())
        ->where('topic_id', $topic->id)
        ->exists();
    }

    return response()->json([
      'post' => [
        'id' => $topic->id,
        'title' => $topic->title,
        'description' => $topic->description,
        'content' => $topic->content_html,
        'mentions' => $this->resolveTopicMentions($topic->description),
        'image_urls' => $imageUrls,
        'document_urls' => $topic->getDocuments()->map(function ($content) {
          return config('app.url') . Storage::url($content->file_path);
        })->all(),
        'document_sizes' => $topic->getDocuments()->map(function ($content) {
          return $content->file_size;
        })->all(),
        'video_urls' => $topic->getVideos()->map(function ($content) {
          return config('app.url') . Storage::url($content->file_path);
        })->all(),
        'votes' => $topic->votes->map(function ($vote) {
          return [
            'username' => $vote->user->username,
            'vote_value' => $vote->vote_value,
            'created_at' => $vote->created_at ? $vote->created_at->toISOString() : null,
            'updated_at' => $vote->updated_at ? $vote->updated_at->toISOString() : null,
          ];
        }),
        'reply_count' => $this->roundToNearestFive($topic->reply_count ?? 0) . '+',
        'view_count' => is_numeric($topic->views_count) ? (int) $topic->views_count : 0,
        'created_at' => $topic->created_at->diffForHumans(),
        'is_edited' => $topic->is_edited,
        'is_muted' => $topic->is_muted,
        'author' => $topic->anonymous ? [
          'username' => 'Ẩn danh',
          'profile_name' => 'Người dùng ẩn danh',
          'verified' => false,
        ] : [
          'username' => $topic->author->username,
          'profile_name' => $topic->author->profile->profile_name ?? null,
          'verified' => $topic->user->profile->verified == 1 ?? false ? true : false,
        ],
        'anonymous' => $topic->anonymous,
        'is_saved' => $isSaved,
        'is_owner' => auth()->check() && $topic->user_id === auth()->id(),
        'comments' => $formattedComments,
        'images' => \App\Models\UserContent::whereIn('id', !empty($topic->cdn_image_id) ? explode(',', $topic->cdn_image_id) : [])->get()->map(function ($img) {
          return [
            'id' => $img->id,
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url($img->file_path)
          ];
        }),
        'documents' => \App\Models\UserContent::whereIn('id', !empty($topic->cdn_document_id) ? explode(',', $topic->cdn_document_id) : [])->get()->map(function ($doc) {
          return [
            'id' => $doc->id,
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url($doc->file_path),
            'name' => $doc->file_name,
            'size' => $doc->file_size
          ];
        }),
        'videos' => \App\Models\UserContent::whereIn('id', !empty($topic->cdn_video_id) ? explode(',', $topic->cdn_video_id) : [])->get()->map(function ($vid) {
          return [
            'id' => $vid->id,
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url($vid->file_path)
          ];
        }),
      ],
      'ogImage' => $ogImage,
      'comments' => $formattedComments
    ]);
  }

  /**
   * Store a newly created topic in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function store(Request $request)
  {
    // Debug: Log the incoming request data
    \Log::info('Topic creation request data:', ['data' => $request->all()]);
    \Log::info('Request method:', ['method' => $request->method()]);
    \Log::info('Request headers:', ['headers' => $request->headers->all()]);

    // Check if user is authenticated
    if (!auth()->check()) {
      return response()->json([
        'message' => 'Bạn cần đăng nhập để tạo bài viết'
      ], 401);
    }

    // Check if user has verified their email
    $user = auth()->user();
    if (!$user->email_verified_at) {
      return response()->json([
        'message' => 'Bạn cần xác minh email để tạo cuộc thảo luận'
      ], 403);
    }

    // Validate the request data
    $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'required|string',
      'subforum_id' => 'nullable|exists:cyo_forum_subforums,id',  // Kiểm tra subforum_id
      'image_files' => 'nullable|array',
      'image_files.*' => 'file|image|max:10240',  // 10MB max for each image
      'cdn_image_id' => 'nullable|string',  // For mobile app: comma-separated IDs of already uploaded images
      'document_files' => 'nullable|array',
      'document_files.*' => 'file|mimes:pdf,doc,docx,txt|max:25600',  // 25MB max for each document
      'cdn_document_id' => 'nullable|string',  // For mobile app: comma-separated IDs of already uploaded documents
      'video_files' => 'nullable|array',
      'video_files.*' => 'file|mimes:mp4,mov,avi,webm,mkv|max:102400',  // 100MB max for each video
      'cdn_video_id' => 'nullable|string',  // For mobile app: comma-separated IDs of already uploaded videos
      'visibility' => 'nullable|integer|in:0,1',  // 0: public, 1: private (for hidden field)
      'privacy' => 'nullable|string|in:public,followers,private',  // public, followers, private
      'anonymous' => 'nullable|boolean',  // Anonymous posting
      'is_muted' => 'nullable|boolean',  // Video muted status
    ]);

    $cdnImageIds = [];
    $cdnImageId = null;

    // Handle multiple image uploads if present (for web app)
    if ($request->hasFile('image_files')) {
      $files = $request->file('image_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('images', $fileName, 'public');

        // Create UserContent record for each image
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnImageIds[] = $userContent->id;
      }

      // Convert array of IDs to comma-separated string for database storage
      $cdnImageId = !empty($cdnImageIds) ? implode(',', $cdnImageIds) : null;
    }
    // Handle cdn_image_id from mobile app (already uploaded images)
    // Only use if no image_files were uploaded
    elseif ($request->has('cdn_image_id') && !empty($request->cdn_image_id)) {
      // Mobile app sends comma-separated string of UserContent IDs
      $cdnImageId = $request->cdn_image_id;

      // Validate that all IDs exist and belong to the authenticated user
      $imageIds = array_filter(explode(',', $cdnImageId));
      if (!empty($imageIds)) {
        $validIds = UserContent::where('user_id', auth()->id())
          ->whereIn('id', $imageIds)
          ->pluck('id')
          ->toArray();

        if (count($validIds) !== count($imageIds)) {
          return response()->json([
            'message' => 'Một số ảnh không hợp lệ hoặc không thuộc về bạn'
          ], 400);
        }
      }
    }

    $cdnDocumentIds = [];

    // Handle multiple document uploads if present
    if ($request->hasFile('document_files')) {
      $files = $request->file('document_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $fileName, 'public');

        // Create UserContent record for each document
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnDocumentIds[] = $userContent->id;
      }
    }
    // Handle cdn_document_id from mobile app (already uploaded documents)
    elseif ($request->has('cdn_document_id') && !empty($request->cdn_document_id)) {
      $cdnDocumentId = $request->cdn_document_id;

      // Validate IDs
      $docIds = array_filter(explode(',', $cdnDocumentId));
      if (!empty($docIds)) {
        $validIds = UserContent::where('user_id', auth()->id())
          ->whereIn('id', $docIds)
          ->pluck('id')
          ->toArray();

        if (count($validIds) !== count($docIds)) {
          return response()->json([
            'message' => 'Một số tài liệu không hợp lệ hoặc không thuộc về bạn'
          ], 400);
        }
      }
      $cdnDocumentIds = $docIds;  // Update array for consistency if needed later, though we use $cdnDocumentId directly below
    }

    $cdnDocumentId = !empty($cdnDocumentIds) ? implode(',', $cdnDocumentIds) : null;

    $cdnVideoIds = [];
    $cdnVideoId = null;

    // Handle multiple video uploads if present (for web app)
    if ($request->hasFile('video_files')) {
      $files = $request->file('video_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('videos', $fileName, 'public');

        // Create UserContent record for each video
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnVideoIds[] = $userContent->id;
      }

      $cdnVideoId = !empty($cdnVideoIds) ? implode(',', $cdnVideoIds) : null;
    }
    // Handle cdn_video_id from mobile app (already uploaded videos)
    elseif ($request->has('cdn_video_id') && !empty($request->cdn_video_id)) {
      $cdnVideoId = $request->cdn_video_id;

      // Validate that all IDs exist and belong to the authenticated user
      $videoIds = array_filter(explode(',', $cdnVideoId));
      if (!empty($videoIds)) {
        $validIds = UserContent::where('user_id', auth()->id())
          ->whereIn('id', $videoIds)
          ->pluck('id')
          ->toArray();

        if (count($validIds) !== count($videoIds)) {
          return response()->json([
            'message' => 'Một số video không hợp lệ hoặc không thuộc về bạn'
          ], 400);
        }
      }
    }

    // Convert markdown description to HTML
    $contentHtml = $this->convertMarkdownToHtml($request->description);

    // Turn #hashtags into clickable links and collect them for the hashtag index
    $hashtagResult = HashtagService::linkify($contentHtml);
    $contentHtml = $hashtagResult['html'];

    $topic = Topic::create([
      'user_id' => auth()->id(),
      'title' => $request->title,
      'description' => $request->description,  // Keep original markdown
      'content_html' => $contentHtml,  // Store converted HTML
      'subforum_id' => $request->subforum_id,  // Gán giá trị cho subforum_id
      'cdn_image_id' => $cdnImageId,
      'cdn_document_id' => $cdnDocumentId,
      'cdn_video_id' => $cdnVideoId,
      'hidden' => $request->visibility,
      'privacy' => $request->privacy ?? 'public',  // Default to public if not provided
      'anonymous' => $request->boolean('anonymous', false),  // Default to false if not provided
      'is_muted' => $request->boolean('is_muted', false),  // Default to false if not provided
    ]);

    HashtagService::syncTopicHashtags($topic, $hashtagResult['tags']);

    $this->bumpFeedVersion();

    // Handle @mentions in the post — notify each existing mentioned user
    // (only on creation, not on every edit, matching how comment mentions
    // are handled). "all" can never resolve to a real account since it's a
    // reserved username, so it's silently excluded here already.
    $parsedMentionUsernames = NotificationService::parseMentions($request->description ?? '');
    $resolvedTopicMentions = [];
    if (!empty($parsedMentionUsernames)) {
      $mentionedUsers = AuthAccount::whereIn('username', $parsedMentionUsernames)
        ->select('id', 'username')
        ->get();
      foreach ($mentionedUsers as $mentionedUser) {
        NotificationService::createMentionedNotification($mentionedUser->id, $topic, auth()->id());
        $resolvedTopicMentions[] = ['username' => $mentionedUser->username, 'user_id' => $mentionedUser->id];
      }
    }

    // Debug: Log the created topic
    \Log::info('Topic created successfully:', [
      'topic' => [
        'id' => $topic->id,
        'title' => $topic->title,
        'description' => $topic->description,
        'content_html' => $topic->content_html,
        'user_id' => $topic->user_id,
        'subforum_id' => $topic->subforum_id,
        'cdn_image_id' => $topic->cdn_image_id,
      ]
    ]);

    // Load the user profile to get profile_name
    $author = $topic->user()->with('profile')->first();

    // Check if this is an API request or web request
    if ($request->wantsJson() || $request->is('v1.0/*')) {
      return response()->json([
        'id' => $topic->id,
        'title' => $topic->title,
        'content' => $topic->description,
        'mentions' => $resolvedTopicMentions,
        'image_urls' => $topic->getImageUrls()->map(function ($content) {
          return config('app.url') . Storage::url($content->file_path);
        })->all(),
        'document_urls' => $topic->getDocuments()->map(function ($content) {
          return config('app.url') . Storage::url($content->file_path);
        })->all(),
        'document_sizes' => $topic->getDocuments()->map(function ($content) {
          return $content->file_size;
        })->all(),
        'video_urls' => $topic->getVideos()->map(function ($content) {
          return config('app.url') . Storage::url($content->file_path);
        })->all(),
        'author' => $topic->anonymous ? [
          'id' => null,
          'username' => 'Ẩn danh',
          'email' => null,
          'profile_name' => 'Người dùng ẩn danh',
          'verified' => false,
        ] : [
          'id' => $author->id,
          'username' => $author->username,
          'email' => $author->email,
          'profile_name' => $author->profile->profile_name ?? null,  // Ensure profile_name is included
        ],
        'anonymous' => $topic->anonymous,
        'time' => Carbon::parse($topic->created_at)->diffForHumans(),  // You can dynamically calculate the time difference if needed
        'is_edited' => false,
        'is_muted' => $topic->is_muted,
        'comments' => '00+',  // Adjust this based on actual comment count if necessary
        'views' => 0,  // Initialize view count as 0 or load actual views
        'votes' => [],  // Initialize empty votes array or load actual votes
        'saved' => false,  // Default to false or check if the user has saved the topic
      ], 201);
    }

    // For web requests (Inertia), return a redirect or success response
    return back()->with('success', 'Bài viết đã được tạo thành công!');
  }

  /**
   * Update the specified topic in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function update(Request $request, $id)
  {
    $topic = Topic::findOrFail($id);

    // Check ownership
    if ($topic->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
      return response()->json(['message' => 'Bạn không có quyền chỉnh sửa bài viết này'], 403);
    }

    $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'required|string',
      'subforum_id' => 'nullable|exists:cyo_forum_subforums,id',
      'image_files' => 'nullable|array',
      'image_files.*' => 'file|image|max:10240',
      'document_files' => 'nullable|array',
      'document_files.*' => 'file|mimes:pdf,doc,docx,txt|max:25600',
      'video_files' => 'nullable|array',
      'video_files.*' => 'file|mimes:mp4,mov,avi,webm,mkv|max:102400',
      'visibility' => 'nullable|integer|in:0,1',
      'privacy' => 'nullable|string|in:public,followers,private',
      'anonymous' => 'nullable|boolean',
      'is_muted' => 'nullable|boolean',
    ]);

    // Initialize ID arrays from request 'kept_ids' (allows deletion)
    // We expect the frontend to send the comma-separated list of IDs it wants to KEEP from the original set
    // If kept_image_ids is NOT present in request, we fallback to existing DB (assume no deletion intended if field missing?
    // Or strictly require it? Better to fallback to DB if missing to prevent accidental wipe if frontend doesn't send it)
    if ($request->has('kept_image_ids')) {
      $cdnImageIds = !empty($request->kept_image_ids) ? explode(',', $request->kept_image_ids) : [];
      // Optional: Validate that these IDs were indeed part of the original topic or belong to user
      // For simplicity allow provided list, but filtering against original is safer practice:
      // $originalIds = !empty($topic->cdn_image_id) ? explode(',', $topic->cdn_image_id) : [];
      // $cdnImageIds = array_intersect($cdnImageIds, $originalIds);
    } else {
      $cdnImageIds = !empty($topic->cdn_image_id) ? explode(',', $topic->cdn_image_id) : [];
    }

    if ($request->has('kept_document_ids')) {
      $cdnDocumentIds = !empty($request->kept_document_ids) ? explode(',', $request->kept_document_ids) : [];
    } else {
      $cdnDocumentIds = !empty($topic->cdn_document_id) ? explode(',', $topic->cdn_document_id) : [];
    }

    if ($request->has('kept_video_ids')) {
      $cdnVideoIds = !empty($request->kept_video_ids) ? explode(',', $request->kept_video_ids) : [];
    } else {
      $cdnVideoIds = !empty($topic->cdn_video_id) ? explode(',', $topic->cdn_video_id) : [];
    }

    // Filter out any empty values
    $cdnImageIds = array_filter($cdnImageIds);
    $cdnDocumentIds = array_filter($cdnDocumentIds);
    $cdnVideoIds = array_filter($cdnVideoIds);

    // Handle new image uploads
    if ($request->hasFile('image_files')) {
      $files = $request->file('image_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('images', $fileName, 'public');

        // Create UserContent record for each image
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnImageIds[] = $userContent->id;
      }
    }

    // Handle new document uploads
    if ($request->hasFile('document_files')) {
      $files = $request->file('document_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $fileName, 'public');

        // Create UserContent record for each document
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnDocumentIds[] = $userContent->id;
      }
    }

    // Handle new video uploads
    if ($request->hasFile('video_files')) {
      $files = $request->file('video_files');

      foreach ($files as $file) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = time() . '_' . uniqid() . '_' . Str::slug($originalName) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('videos', $fileName, 'public');

        // Create UserContent record for each video
        $userContent = UserContent::create([
          'user_id' => auth()->id(),
          'file_name' => $fileName,
          'file_path' => $path,
          'file_type' => $file->getMimeType(),
          'file_size' => $file->getSize(),
        ]);

        $cdnVideoIds[] = $userContent->id;
      }
    }

    // Convert markdown to HTML
    $contentHtml = $this->convertMarkdownToHtml($request->description);

    // Turn #hashtags into clickable links and collect them for the hashtag index
    $hashtagResult = HashtagService::linkify($contentHtml);
    $contentHtml = $hashtagResult['html'];

    $topic->title = $request->title;
    $topic->description = $request->description;
    $topic->content_html = $contentHtml;
    $topic->subforum_id = $request->subforum_id;
    $topic->cdn_image_id = !empty($cdnImageIds) ? implode(',', $cdnImageIds) : null;
    $topic->cdn_document_id = !empty($cdnDocumentIds) ? implode(',', $cdnDocumentIds) : null;
    $topic->cdn_video_id = !empty($cdnVideoIds) ? implode(',', $cdnVideoIds) : null;
    $topic->hidden = $request->visibility ?? 0;
    $topic->privacy = $request->privacy ?? 'public';
    $topic->anonymous = $request->boolean('anonymous', false);

    $topic->save();

    HashtagService::syncTopicHashtags($topic, $hashtagResult['tags']);

    // Resolve @mentions in the updated text so clients can render them
    // immediately, matching updateComment() — no new notifications are
    // sent on edit, only on the initial post creation.
    $topicArray = $topic->toArray();
    $topicArray['mentions'] = $this->resolveTopicMentions($topic->description);

    return response()->json([
      'message' => 'Bài viết đã được cập nhật thành công',
      'data' => $topicArray
    ]);
  }

  /**
   * Get the views for a specific topic.
   *
   * @param  int  $topicId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getViews($topicId)
  {
    $views = TopicView::where('topic_id', $topicId)->get();
    return response()->json($views);
  }

  /**
   * Register a view for a specific topic.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $topicId
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function registerView(Request $request, $topicId)
  {
    // Check if the topic exists
    $topic = Topic::findOrFail($topicId);
    $userId = auth()->check() ? auth()->id() : null;

    // Register the view (allow multiple views)
    TopicView::create([
      'topic_id' => $topic->id,
      'user_id' => $userId,
    ]);

    // For API requests (mobile app), return JSON
    if ($request->expectsJson()) {
      return response()->json(['message' => 'View registered successfully'], 201);
    }

    // For web requests (Inertia), return a redirect or success response
    return back()->with('success', 'View tracked successfully');
  }

  /**
   * Get the votes for a specific topic.
   *
   * @param  int  $topicId
   * @return \Illuminate\Http\JsonResponse
   */
  public function getVotes($topicId)
  {
    $votes = TopicVote::with('user.profile')
      ->where('topic_id', $topicId)
      ->get()
      ->map(function ($vote) {
        $user = $vote->user;
        return [
          'user_id' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? null,
          'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
          'vote_value' => $vote->vote_value,
        ];
      });

    return response()->json($votes);
  }

  /**
   * Register a vote for a specific topic.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $topicId
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function registerVote(Request $request, $topicId)
  {
    $request->validate([
      'vote_value' => 'required|integer|in:1,-1,0',  // 1 for upvote, -1 for downvote, 0 for remove vote
    ]);

    // Retrieve the authenticated user
    $user = Auth::user();

    $vote = TopicVote::updateOrCreate(
      [
        'topic_id' => $topicId,
        'user_id' => auth()->id(),
      ],
      [
        'vote_value' => $request->input('vote_value'),
      ]
    );

    // If vote_value is 0, delete the vote
    if ($request->input('vote_value') == 0) {
      $vote->delete();
    }

    // Create notification for topic like (only for upvote)
    if ((int) $request->input('vote_value') !== 0) {
      $topic = Topic::find($topicId);
      if ($topic) {
        NotificationService::createTopicLikedNotification($topic, $user->id, (int) $request->input('vote_value'));
      }
    }

    if ($request->wantsJson()) {
      return response()->json([
        'message' => 'Vote registered',
        'vote_value' => $request->input('vote_value'),
        'total_votes' => TopicVote::where('topic_id', $topicId)->sum('vote_value'),
        'vote' => [
          'vote_value' => $vote->vote_value,
          'username' => $user->username
        ]
      ]);
    }

    return redirect()->back()->with('success', 'Đã vote bài viết thành công');
  }

  /**
   * Get the comments for a specific topic.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $topicId
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Support\Collection
   */
  public function getComments(Request $request, $topicId)
  {
    $comments = TopicComment::with(['user', 'user.profile'])
      ->where('topic_id', $topicId)
      ->orderBy('created_at', 'desc')
      ->get();

    // Batch-resolve @mentions so we avoid N+1 lookups
    $allTexts = $comments->pluck('comment')->filter()->all();
    $allUsernames = empty($allTexts) ? [] : array_unique(array_merge(
      ...array_map(fn($t) => \App\Services\NotificationService::parseMentions($t), $allTexts)
    ));
    $resolvedUsers = [];
    if (!empty($allUsernames)) {
      foreach (\App\Models\AuthAccount::whereIn('username', $allUsernames)->select('id', 'username')->get() as $u) {
        $resolvedUsers[strtolower($u->username)] = ['username' => $u->username, 'user_id' => $u->id];
      }
    }
    $resolveMentions = function (?string $text) use ($resolvedUsers): array {
      if (!$text) return [];
      $result = [];
      foreach (\App\Services\NotificationService::parseMentions($text) as $un) {
        $key = strtolower($un);
        if (isset($resolvedUsers[$key])) $result[] = $resolvedUsers[$key];
      }
      return $result;
    };

    $mapped = $comments->map(function ($comment) use ($resolveMentions) {
        $isOwner = auth()->check() && $comment->user_id === auth()->id();

        return [
          'id' => $comment->id,
          'topic_id' => $comment->topic_id,
          'content' => $comment->comment,  // Raw markdown text for editing
          'comment' => $comment->comment_html,  // HTML for display
          'is_anonymous' => $comment->is_anonymous,
          'is_owner' => $isOwner,  // Add ownership flag
          'created_at' => $comment->created_at,
          'updated_at' => $comment->updated_at,
          'is_edited' => $comment->is_edited,
          'mentions' => $resolveMentions($comment->comment),
          'author' => $comment->is_anonymous ? [
            'id' => null,
            'username' => 'Người dùng ẩn danh',
            'email' => null,
            'profile_name' => 'Người dùng ẩn danh',
            'verified' => false,
          ] : [
            'id' => $comment->user->id,
            'username' => $comment->user->username,
            'email' => $comment->user->email,
            'profile_name' => $comment->user->profile->profile_name ?? null,  // Handle case where profile might not exist
            'verified' => $comment->user->profile->verified == 1 ?? false ? true : false,
          ],
        ];
      });

    if ($request->wantsJson()) {
      return response()->json($mapped);
    }

    return $mapped;
  }

  /**
   * Update the specified comment in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function updateComment(Request $request, $id)
  {
    $request->validate([
      'comment' => 'required|string',
    ]);

    $comment = TopicComment::findOrFail($id);

    // Check if user owns the comment
    if ($comment->user_id !== auth()->id()) {
      if ($request->wantsJson()) {
        return response()->json(['message' => 'Unauthorized'], 403);
      }
      return redirect()->back()->withErrors(['comment' => 'Bạn không có quyền sửa bình luận này']);
    }

    $comment->update([
      'comment' => $request->comment,  // Store raw markdown/text
      'comment_html' => $this->convertMarkdownToHtml($request->comment),  // Store processed HTML
    ]);

    if ($request->wantsJson()) {
      // Load the comment's author profile details
      $author = $comment->user()->with('profile')->first();

      // Resolve @mentions in the updated text so clients can render them immediately
      $parsedMentionUsernames = NotificationService::parseMentions($request->comment ?? '');
      $resolvedMentions = [];
      if (!empty($parsedMentionUsernames)) {
        foreach (\App\Models\AuthAccount::whereIn('username', $parsedMentionUsernames)->select('id', 'username')->get() as $u) {
          $resolvedMentions[] = ['username' => $u->username, 'user_id' => $u->id];
        }
      }

      return response()->json([
        'id' => $comment->id,
        'content' => $comment->comment,  // Return raw markdown for editing
        'comment' => $comment->comment_html,  // Return HTML for display
        'is_owner' => true,  // Updated comment is always owned by updater
        'mentions' => $resolvedMentions,  // Resolved @mention targets for immediate rendering
        'author' => [
          'id' => $author->id,
          'username' => $author->username,
          'profile_name' => $author->profile->profile_name ?? null,
          'verified' => $author->profile->verified == 1 ?? false ? true : false,
        ],
        'created_at' => Carbon::parse($comment->created_at)->diffForHumans(),
        'is_edited' => $comment->is_edited,
        'votes' => $comment->votes,
      ]);
    }

    return redirect()->back()->with('success', 'Bình luận đã được cập nhật thành công');
  }

  /**
   * Get the replies for a specific comment.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $commentId
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\Pagination\LengthAwarePaginator
   */
  public function getReplies(Request $request, $commentId)
  {
    $comment = TopicComment::findOrFail($commentId);

    $replies = $comment
      ->replies()
      ->paginate(5);  // Load replies in chunks

    if ($request->wantsJson()) {
      return response()->json($replies);
    }

    return $replies;
  }

  /**
   * Add a comment to a topic.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function addComment(Request $request)
  {
    $request->validate([
      'comment' => 'required_without:images|nullable|string',
      'images' => 'nullable|array|max:10',
      'images.*' => 'file|image|max:10240|mimes:jpeg,png,jpg,gif,webp',
      'replying_to' => 'nullable|exists:cyo_topic_comments,id',
      'topic_id' => 'required|exists:cyo_topics,id',
      'is_anonymous' => 'nullable|boolean',
    ]);

    $imagePaths = [];
    if ($request->hasFile('images')) {
      foreach ($request->file('images') as $image) {
        $path = $image->store('comment_images', 'public');
        ProcessImageCompression::dispatch($path);
        $imagePaths[] = $path;
      }
    }

    // Cap nesting at 3 levels: if the target comment is already level 3+
    // (i.e. its own parent also has a parent), attach to the level-2 ancestor instead.
    // Store the original target in target_comment_id so the UI can sandwich the reply
    // right after the actual comment being replied to.
    $originalReplyingTo = $request->replying_to;
    $replyingTo = $originalReplyingTo;
    $targetCommentId = null;

    if ($replyingTo) {
      $target = TopicComment::find($replyingTo);
      if ($target && $target->replying_to) {
        $grandparent = TopicComment::find($target->replying_to);
        if ($grandparent && $grandparent->replying_to) {
          // target is level 4+; walk up until we reach level 2
          $replyingTo = $grandparent->replying_to;
          $targetCommentId = $originalReplyingTo;
        } elseif ($grandparent) {
          // target is level 3; cap to level 2 (its parent), keep target for ordering
          $replyingTo = $target->replying_to;
          $targetCommentId = $originalReplyingTo;
        }
      }
    }

    $comment = TopicComment::create([
      'replying_to' => $replyingTo,
      'target_comment_id' => $targetCommentId,
      'topic_id' => $request->topic_id,
      'user_id' => auth()->id(),
      'comment' => $request->comment ?? '',
      'comment_html' => $request->comment ? $this->convertMarkdownToHtml($request->comment) : '',
      'image_urls' => !empty($imagePaths) ? $imagePaths : null,
      'is_anonymous' => $request->boolean('is_anonymous', false),
    ]);

    // Load the comment's author profile details
    $author = $comment->user()->with('profile')->first();

    // Load the topic for notification
    $topic = Topic::find($request->topic_id);

    // Create notification for topic being commented on (only if not a reply)
    if ($topic && !$request->replying_to) {
      NotificationService::createTopicCommentedNotification($topic, $comment, auth()->id());
    }

    // Handle mentions in comment — resolve usernames to {username, user_id} pairs for the response
    $parsedMentionUsernames = NotificationService::parseMentions($request->comment ?? '');
    $resolvedMentions = [];
    if (!empty($parsedMentionUsernames) && $topic) {
      $mentionedUsers = \App\Models\AuthAccount::whereIn('username', $parsedMentionUsernames)
        ->select('id', 'username')
        ->get();
      foreach ($mentionedUsers as $mentionedUser) {
        NotificationService::createMentionedNotification($mentionedUser->id, $comment, auth()->id());
        $resolvedMentions[] = ['username' => $mentionedUser->username, 'user_id' => $mentionedUser->id];
      }
    }

    $commentData = [
      'id' => $comment->id,
      'content' => $comment->comment,  // Return raw markdown for editing
      'comment' => $comment->comment_html,  // Return HTML for display
      'is_anonymous' => $comment->is_anonymous,
      'is_owner' => true,  // New comment is always owned by creator
      'mentions' => $resolvedMentions,  // Resolved @mention targets for immediate rendering
      'author' => $comment->is_anonymous ? [
        'id' => null,
        'username' => 'Người dùng ẩn danh',
        'profile_name' => 'Người dùng ẩn danh',
        'verified' => false,
      ] : [
        'id' => $author->id,
        'username' => $author->username,
        'profile_name' => $author->profile->profile_name ?? null,
        'verified' => $author->profile->verified == 1 ?? false ? true : false,
      ],
      'created_at' => Carbon::parse($comment->created_at)->diffForHumans(),
      'is_edited' => false,
      'image_urls' => $comment->image_urls
        ? array_map(fn($p) => config('app.url') . Storage::url($p), $comment->image_urls)
        : [],
      'votes' => [],
    ];

    if ($request->wantsJson()) {
      return response()->json($commentData, 201);
    }

    return redirect()->back()->with([
      'success' => 'Bình luận đã được thêm thành công',
      'comment' => $commentData,
    ]);
  }

  /**
   * Register a vote on a specific comment.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $commentId
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function voteOnComment(Request $request, $commentId)
  {
    $request->validate([
      'vote_value' => 'required|integer|in:1,-1,0',  // 1 for upvote, -1 for downvote, 0 for remove vote
    ]);

    $vote = TopicCommentVote::updateOrCreate(
      [
        'comment_id' => $commentId,
        'user_id' => auth()->id(),
      ],
      [
        'vote_value' => $request->input('vote_value'),
      ]
    );

    // If vote_value is 0, delete the vote
    if ($request->input('vote_value') == 0) {
      $vote->delete();
    }

    // Create notification for comment like (only for upvote)
    if ((int) $request->input('vote_value') !== 0) {
      $comment = TopicComment::find($commentId);
      if ($comment) {
        NotificationService::createCommentLikedNotification($comment, auth()->id(), (int) $request->input('vote_value'));
      }
    }

    if ($request->wantsJson()) {
      return response()->json([
        'message' => 'Vote registered on comment',
        'vote_value' => $request->input('vote_value'),
        'total_votes' => TopicCommentVote::where('comment_id', $commentId)->sum('vote_value')
      ], 201);
    }

    return redirect()->back()->with('success', 'Đã vote bình luận thành công');
  }

  /**
   * Get the votes for a specific comment.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|array
   */
  public function getVotesForComment(Request $request, $id)
  {
    $comment = TopicComment::findOrFail($id);

    $votes = TopicCommentVote::with('user.profile')
      ->where('comment_id', $comment->id)
      ->get()
      ->map(function ($vote) {
        $user = $vote->user;
        return [
          'user_id' => $user->id,
          'username' => $user->username,
          'profile_name' => $user->profile->profile_name ?? null,
          'avatar_url' => config('app.url') . "/v1.0/users/{$user->username}/avatar",
          'vote_value' => $vote->vote_value,
        ];
      });

    $data = [
      'comment_id' => $comment->id,
      'comment' => $comment->comment,
      'votes' => $votes,
    ];

    if ($request->wantsJson()) {
      return response()->json($data);
    }

    return $data;
  }

  /**
   * Get the saved topics for the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSavedTopics()
  {
    $userId = Auth::id();

    $result = UserSavedTopic::where('user_id', $userId)
      ->with(['topic.user.profile'])
      ->orderBy('created_at', 'desc')
      ->get();

    if ($result->isEmpty()) {
      return response()->json([]);
    }

    $mappedTopics = $result->map(function ($savedTopic) {
      return [
        'id' => $savedTopic->id,
        'user_id' => $savedTopic->user_id,
        'topic_id' => $savedTopic->topic_id,
        'created_at' => $savedTopic->created_at->diffForHumans(),
        'updated_at' => $savedTopic->updated_at,
        'topic' => [
          'id' => $savedTopic->topic->id,
          'subforum_id' => $savedTopic->topic->subforum_id,
          'user_id' => $savedTopic->topic->user->id,
          'title' => $savedTopic->topic->title,
          'content' => $savedTopic->topic->description,
          'anonymous' => $savedTopic->topic->anonymous,
          'created_at' => $savedTopic->topic->created_at,
          'updated_at' => $savedTopic->topic->updated_at,
          'is_edited' => $savedTopic->topic->is_edited,
          'pinned' => $savedTopic->topic->pinned,
          'image_urls' => $savedTopic->topic->getImageUrls()->map(function ($content) {
            return config('app.url') . Storage::url($content->file_path);
          })->all(),
          'video_urls' => $savedTopic->topic->getVideos()->map(function ($content) {
            return config('app.url') . Storage::url($content->file_path);
          })->all(),
          'author' => [
            'id' => $savedTopic->topic->user->id,
            'username' => $savedTopic->topic->user->username,
            'email' => $savedTopic->topic->user->email,
            'profile_name' => $savedTopic->topic->user->profile->profile_name ?? null,
          ],
        ]
      ];
    });

    return response()->json($mappedTopics);
  }

  /**
   * Save a topic for the authenticated user.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function saveTopicForUser(Request $request)
  {
    $request->validate([
      'topic_id' => 'required|exists:cyo_topics,id',  // Validate that the topic exists
    ]);

    $userId = auth()->id();  // Get authenticated user ID

    // Check if the user has already saved the topic
    $exists = DB::table('cyo_user_saved_topics')
      ->where('topic_id', $request->topic_id)
      ->where('user_id', $userId)
      ->exists();

    if ($exists) {
      // If the record exists, return a message or take appropriate action
      return response()->json(['message' => 'This topic is already saved by the user.'], 409);  // 409 Conflict
    }

    UserSavedTopic::create([
      'user_id' => $userId,
      'topic_id' => $request->topic_id,
    ]);

    return response()->json(['message' => 'Topic saved successfully.']);
  }

  /**
   * Remove the specified topic from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroyTopic($id)
  {
    $topic = Topic::findOrFail($id);
    $topic->delete();

    return response()->json(['message' => 'Topic deleted successfully.'], Response::HTTP_OK);
  }

  /**
   * Remove the specified topic vote from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroyTopicVote($id)
  {
    $vote = TopicVote::findOrFail($id);
    $vote->delete();

    return response()->json(['message' => 'Vote deleted successfully.'], Response::HTTP_OK);
  }

  /**
   * Remove the specified saved topic from storage for the authenticated user.
   *
   * @param  int  $topicId
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroySavedTopic($topicId, Request $request)
  {
    // Optionally, validate the user is authenticated
    $userId = $request->user()->id;  // Get the authenticated user's ID

    // Find the saved topic by topic_id and user_id
    $savedTopic = UserSavedTopic::where('topic_id', $topicId)
      ->where('user_id', $userId)
      ->first();

    if (!$savedTopic) {
      return response()->json(['message' => 'Saved topic not found'], 404);
    }

    // Delete the saved topic
    $savedTopic->delete();

    return response()->json(['message' => 'Saved topic deleted successfully'], 200);
  }

  /**
   * Remove the specified comment from storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function destroyComment(Request $request, $id)
  {
    $comment = TopicComment::findOrFail($id);

    // Check if user owns the comment
    if ($comment->user_id !== auth()->id()) {
      if ($request->wantsJson()) {
        return response()->json(['message' => 'Unauthorized'], 403);
      }
      return redirect()->back()->withErrors(['comment' => 'Bạn không có quyền xóa bình luận này']);
    }

    // Get the parent comment ID (if this comment has a parent)
    $parentCommentId = $comment->replying_to;

    // Get username of the comment being deleted (before deletion)
    $deletedCommentUsername = $comment->is_anonymous ? 'Người dùng ẩn danh' : $comment->user->username;

    // Only get direct children IDs (not all descendants)
    // We only promote direct children by 1 level, keeping nested structure intact
    $directChildrenIds = TopicComment::where('replying_to', $id)->pluck('id')->toArray();

    // Promote direct children:
    // - If deleting level 1 comment (no parent), promote direct children to level 1 (replying_to = null)
    // - If deleting level 2+ comment (has parent), promote direct children to reply directly to the parent
    // Note: Nested children (level 3, 4, etc.) keep their structure - they remain replies to their immediate parent
    // Important: Do not update updated_at timestamp when promoting children - use DB::table() for direct update
    // Also save the username of the deleted comment so children can display a notification
    if (!empty($directChildrenIds)) {
      DB::table('cyo_topic_comments')
        ->whereIn('id', $directChildrenIds)
        ->update([
          'replying_to' => $parentCommentId,
          'deleted_parent_username' => $deletedCommentUsername
        ]);
    }

    // Delete the comment
    $comment->delete();

    if ($request->wantsJson()) {
      return response()->json(['message' => 'Comment deleted successfully.'], 200);
    }

    return redirect()->back()->with('success', 'Bình luận đã được xóa thành công');
  }

  /**
   * Remove the specified comment vote from storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
   */
  public function destroyCommentVote(Request $request, $id)
  {
    $user = $request->user();

    if (!$user) {
      return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Find the vote by comment_id and user_id
    $vote = TopicCommentVote::where('comment_id', $id)
      ->where('user_id', $user->id)
      ->first();

    if (!$vote) {
      return response()->json(['error' => 'Vote not found'], 404);
    }

    $vote->delete();

    return response()->json(['message' => 'Comment vote deleted successfully.'], Response::HTTP_OK);
  }

  /**
   * Get all public topics for sitemap generation.
   * Only returns minimal data: id, title, username, anonymous, created_at, updated_at
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSitemapTopics()
  {
    // Only get public, non-hidden topics
    $topics = Topic::select([
      'id',
      'title',
      'user_id',
      'anonymous',
      'created_at',
      'updated_at'
    ])
      ->where('hidden', 0)
      ->where('privacy', 'public')
      ->orderBy('created_at', 'desc')
      ->with(['user:id,username'])
      ->get();

    // Format the response with minimal data
    $formattedTopics = $topics->map(function ($topic) {
      return [
        'id' => $topic->id,
        'title' => $topic->title,
        'username' => $topic->anonymous ? 'anonymous' : ($topic->user->username ?? 'anonymous'),
        'anonymous' => $topic->anonymous,
        'created_at' => $topic->created_at ? $topic->created_at->toISOString() : null,
        'updated_at' => $topic->updated_at ? $topic->updated_at->toISOString() : null,
      ];
    });

    return response()->json($formattedTopics);
  }

  /**
   * Suggest users to mention (@) in forum comments/topics.
   * Searches across all users by username or profile name.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function mentionSuggestions(Request $request)
  {
    $request->validate(['q' => 'required|string|min:1|max:50']);

    $currentUserId = auth()->id();
    $query = $request->input('q');

    $users = AuthAccount::query()
      ->when($currentUserId, fn($q) => $q->where('id', '!=', $currentUserId))
      ->where(function ($q) use ($query) {
        $q->whereRaw('LOWER(username) LIKE ?', ['%' . strtolower($query) . '%'])
          ->orWhereHas('profile', function ($q2) use ($query) {
            $q2->whereRaw('LOWER(profile_name) LIKE ?', ['%' . strtolower($query) . '%']);
          });
      })
      ->with('profile')
      ->limit(10)
      ->get()
      ->map(fn($u) => [
        'id' => $u->id,
        'username' => $u->username,
        'profile_name' => $u->profile->profile_name ?? $u->username,
        'avatar_url' => config('app.url') . "/v1.0/users/{$u->username}/avatar",
      ]);

    return response()->json(['suggestions' => $users]);
  }
}
