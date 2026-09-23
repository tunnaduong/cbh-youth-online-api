<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Jobs\ProcessImageCompression;
use App\Jobs\ProcessVideoCompression;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Story;
use App\Models\StoryReaction;
use App\Models\StoryViewer;
use App\Models\UserBlock;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\Process\Process;

/**
 * Handles the logic for creating, viewing, and interacting with user stories.
 */
class StoryController extends Controller
{
    /**
     * Get all active stories, grouped by user.
     * For authenticated users, it shows public and followers' stories.
     * For non-authenticated users, it shows only public stories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // For logged-out users, only show public stories
        // For authenticated users, show public and followers stories
        $privacyLevels = $request->user() ? ['public', 'followers'] : ['public'];

        $query = Story::with(['user', 'viewers', 'reactions'])
            ->active()
            ->whereIn('privacy', $privacyLevels);

        if ($request->user()) {
            // Bidirectional: also hide stories from someone who has blocked
            // the viewer, not just people the viewer blocked themselves.
            $blockedEitherWayIds = array_values(array_unique(array_merge(
                \App\Models\UserBlock::where('user_id', $request->user()->id)->pluck('blocked_user_id')->toArray(),
                \App\Models\UserBlock::where('blocked_user_id', $request->user()->id)->pluck('user_id')->toArray()
            )));
            $query->whereNotIn('user_id', $blockedEitherWayIds);
        }

        $stories = $query
            ->orderByDesc('pinned')  // Order by pinned first
            ->orderBy('created_at', 'asc')  // Order by oldest first so latest stories appear at end
            ->get()
            ->groupBy('user_id')
            ->map(function ($userStories, $userId) {
                $firstStory = $userStories->first();
                $user = $firstStory->user;

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->profile->profile_name ?? $user->username,
                    'stories' => $userStories->map(function ($story) {
                        return [
                            'id' => (string) $story->id,
                            'media_url' => $story->media_url,
                            'video_first_frame_url' => $story->video_first_frame_url,
                            'text_content' => $story->content,
                            'type' => $story->media_type,
                            'background_color' => $story->background_color,
                            'font_style' => $story->font_style,
                            'text_position' => $story->text_position,
                            'overlays' => $story->overlays,
                            'music' => $story->music,
                            'created_at' => $story->created_at ? $story->created_at->toISOString() : null,
                            'created_at_human' => $story->created_at->diffForHumans(),
                            'duration' => $story->duration ?? 10,
                            'expires_at' => $story->expires_at,
                            'user_id' => $story->user_id,
                            'pinned' => $story->pinned,  // Add pinned status
                            'is_muted' => $story->is_muted,
                            'viewers' => $story->viewers->where('user_id', '!=', $story->user_id)->values(),
                            'reactions' => $story->reactions->map(function ($reaction) {
                                return [
                                    'type' => $reaction->reaction_type,
                                    'user' => $reaction->user->username,
                                ];
                            }),
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $stories,
            ]);
        }

        // For Inertia requests, return JSON with proper structure
        return response()->json($stories);
    }

    /**
     * Store a newly created story in storage.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Base validation rules
        $rules = [
            'content' => 'nullable|string',
            'media_type' => 'required|in:image,video,audio,text',
            'privacy' => 'required|in:public,followers,private',
            'duration' => 'nullable|integer|min:1|max:30',
            'expires_at' => 'nullable|date',
            'is_muted' => 'nullable',
            'overlays' => 'nullable',
            'music' => 'nullable',
        ];

        // Add media-specific validation based on media_type
        if ($request->media_type === 'text') {
            $rules['background_color'] = 'nullable|string';
            $rules['font_style'] = 'nullable|string';
            $rules['text_position'] = 'nullable|json';
        } else {
            $rules['media_file'] = 'required|file|mimes:jpeg,png,jpg,gif,heic,heif,mp4,mov,mp3,wav|max:100240';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['user_id'] = Auth::id();

        if ($request->has('is_muted')) {
            $rawMuted = $request->input('is_muted');

            if (is_bool($rawMuted)) {
                $data['is_muted'] = $rawMuted;
            } elseif (is_string($rawMuted)) {
                $data['is_muted'] = in_array(strtolower($rawMuted), ['1', 'true', 'yes', 'on'], true);
            } elseif (is_int($rawMuted)) {
                $data['is_muted'] = (bool) $rawMuted;
            } else {
                $data['is_muted'] = false;
            }
        } else {
            $data['is_muted'] = false;
        }

        // Editor overlays / soundtrack arrive as JSON strings because the
        // story itself is posted as multipart form data.
        $data['overlays'] = $this->sanitizeOverlays($request->input('overlays'));
        $data['music'] = $this->sanitizeMusic($request->input('music'));

        // Set expires_at only if not provided
        if (! isset($data['expires_at'])) {
            $data['expires_at'] = Carbon::now()->addHours(24);
        }

        // Handle data conversion for database storage
        if (isset($data['background_color']) && is_array($data['background_color'])) {
            $data['background_color'] = json_encode($data['background_color']);
        }

        if (isset($data['text_position']) && is_array($data['text_position'])) {
            $data['text_position'] = json_encode($data['text_position']);
        }

        // Handle media upload if present
        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');

            if ($request->media_type === 'image' && \App\Services\HeicImageConverter::isHeic($file)) {
                $converted = \App\Services\HeicImageConverter::convertAndStore($file, 'stories');
                if ($converted === null) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Không thể xử lý ảnh HEIC này, vui lòng thử ảnh khác.',
                    ], 422);
                }
                $path = $converted['path'];
            } else {
                $path = $file->store('stories', 'public');
            }

            $data['media_url'] = Storage::url($path);

            if ($request->media_type === 'video') {
                ProcessVideoCompression::dispatch($path);
                $data['video_first_frame_url'] = $this->createVideoPreviewGif($path);
            } elseif ($request->media_type === 'image') {
                ProcessImageCompression::dispatch($path);
            }
        }

        $story = Story::create($data);

        $this->notifyStoryMentions($story);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $story->load(['user', 'viewers', 'reactions']),
            ], 201);
        }

        return back()->with('success', 'Story created successfully!');
    }

    /**
     * Overlay item types the editor is allowed to persist.
     *
     * @var array<int, string>
     */
    private const OVERLAY_TYPES = ['text', 'sticker', 'mention', 'link', 'music'];

    /**
     * Normalize and harden the overlay payload sent by the story editor.
     *
     * Coordinates are stored normalized (0..1) against the 9:16 canvas so any
     * client can lay the overlays out at its own screen size. Everything is
     * clamped and length-capped here because it is rendered back to other
     * users verbatim.
     *
     * @param  mixed  $raw  JSON string (multipart) or already-decoded array
     * @return array<string, mixed>|null
     */
    private function sanitizeOverlays($raw): ?array
    {
        $payload = $this->decodeJsonPayload($raw);

        if (! is_array($payload)) {
            return null;
        }

        $items = [];

        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = is_string($item['type'] ?? null) ? $item['type'] : null;

            if (! in_array($type, self::OVERLAY_TYPES, true)) {
                continue;
            }

            $clean = [
                'type' => $type,
                'x' => $this->clampFloat($item['x'] ?? 0.5, -1, 2, 0.5),
                'y' => $this->clampFloat($item['y'] ?? 0.5, -1, 2, 0.5),
                'scale' => $this->clampFloat($item['scale'] ?? 1, 0.1, 8, 1),
                'rotation' => $this->clampFloat($item['rotation'] ?? 0, -360, 360, 0),
                'width' => $this->clampFloat($item['width'] ?? 0.8, 0.05, 2, 0.8),
            ];

            switch ($type) {
                case 'text':
                    $text = trim((string) ($item['text'] ?? ''));

                    if ($text === '') {
                        continue 2;
                    }

                    $clean['text'] = Str::limit($text, 500, '');
                    $clean['color'] = $this->sanitizeColor($item['color'] ?? null, '#FFFFFF');
                    $clean['font'] = $this->sanitizeSlug($item['font'] ?? null, 'classic');
                    $clean['effect'] = $this->sanitizeSlug($item['effect'] ?? null, 'none');
                    $clean['align'] = in_array($item['align'] ?? null, ['left', 'center', 'right'], true)
                        ? $item['align']
                        : 'center';
                    $clean['fontSize'] = $this->clampFloat($item['fontSize'] ?? 0.08, 0.01, 0.4, 0.08);
                    break;

                case 'sticker':
                    $emoji = trim((string) ($item['emoji'] ?? ''));

                    if ($emoji === '') {
                        continue 2;
                    }

                    $clean['emoji'] = Str::limit($emoji, 16, '');
                    break;

                case 'mention':
                    $username = ltrim(trim((string) ($item['username'] ?? '')), '@');

                    if ($username === '') {
                        continue 2;
                    }

                    $clean['username'] = Str::limit($username, 64, '');
                    $clean['user_id'] = isset($item['user_id']) ? (int) $item['user_id'] : null;
                    $clean['style'] = $this->sanitizeSlug($item['style'] ?? null, 'light');
                    break;

                case 'link':
                    $url = $this->sanitizeUrl($item['url'] ?? null);

                    if ($url === null) {
                        continue 2;
                    }

                    $clean['url'] = $url;
                    $clean['label'] = Str::limit(trim((string) ($item['label'] ?? '')), 60, '');
                    $clean['style'] = $this->sanitizeSlug($item['style'] ?? null, 'light');
                    break;

                case 'music':
                    $clean['title'] = Str::limit(trim((string) ($item['title'] ?? '')), 120, '');
                    $clean['artist'] = Str::limit(trim((string) ($item['artist'] ?? '')), 120, '');
                    $clean['artwork_url'] = $this->sanitizeUrl($item['artwork_url'] ?? null, ['apple.com', 'mzstatic.com']);
                    $clean['style'] = $this->sanitizeSlug($item['style'] ?? null, 'light');
                    break;
            }

            $items[] = $clean;

            if (count($items) >= 60) {
                break;
            }
        }

        $filter = $this->sanitizeSlug($payload['filter'] ?? null, 'none');

        if ($items === [] && $filter === 'none') {
            return null;
        }

        return [
            'version' => 1,
            // Image/text stories are flattened into the uploaded picture, so
            // clients only need the items to place invisible tap targets.
            // Video stories keep their overlays live on top of the video.
            'flattened' => (bool) ($payload['flattened'] ?? false),
            'filter' => $filter,
            'items' => $items,
        ];
    }

    /**
     * Normalize the soundtrack payload attached to a story.
     *
     * Only preview URLs served by Apple's iTunes Search API are accepted -
     * the field ends up in an <audio>/player on every client, so it must not
     * become an open redirect to arbitrary media.
     *
     * @param  mixed  $raw  JSON string (multipart) or already-decoded array
     * @return array<string, mixed>|null
     */
    private function sanitizeMusic($raw): ?array
    {
        $payload = $this->decodeJsonPayload($raw);

        if (! is_array($payload)) {
            return null;
        }

        $previewUrl = $this->sanitizeUrl($payload['preview_url'] ?? null, ['apple.com', 'mzstatic.com']);

        if ($previewUrl === null) {
            return null;
        }

        return [
            'provider' => 'itunes',
            'track_id' => isset($payload['track_id']) ? (string) $payload['track_id'] : null,
            'title' => Str::limit(trim((string) ($payload['title'] ?? '')), 120, ''),
            'artist' => Str::limit(trim((string) ($payload['artist'] ?? '')), 120, ''),
            'artwork_url' => $this->sanitizeUrl($payload['artwork_url'] ?? null, ['apple.com', 'mzstatic.com']),
            'preview_url' => $previewUrl,
            'start_ms' => (int) max(0, min(300000, (int) ($payload['start_ms'] ?? 0))),
            'duration_ms' => (int) max(0, min(300000, (int) ($payload['duration_ms'] ?? 0))),
        ];
    }

    /**
     * Notify every user tagged with a mention sticker on a freshly posted story.
     */
    private function notifyStoryMentions(Story $story): void
    {
        $items = $story->overlays['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return;
        }

        $usernames = collect($items)
            ->filter(fn ($item) => ($item['type'] ?? null) === 'mention')
            ->pluck('username')
            ->filter()
            ->unique()
            ->take(20);

        if ($usernames->isEmpty()) {
            return;
        }

        $mentionedUsers = \App\Models\AuthAccount::whereIn('username', $usernames->all())->get();

        foreach ($mentionedUsers as $user) {
            if ($user->id === $story->user_id || $this->isBlockedEitherWay($user->id, $story->user_id)) {
                continue;
            }

            try {
                NotificationService::createStoryMentionNotification($story, $user->id, $story->user_id);
            } catch (\Throwable $e) {
                Log::warning('Failed to send story mention notification', [
                    'story_id' => $story->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Decode a payload that may arrive as a JSON string (multipart) or array.
     *
     * @param  mixed  $raw
     * @return array<mixed>|null
     */
    private function decodeJsonPayload($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function clampFloat($value, float $min, float $max, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return round(max($min, min($max, (float) $value)), 5);
    }

    private function sanitizeColor($value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $fallback;
    }

    private function sanitizeSlug($value, string $fallback): string
    {
        return is_string($value) && preg_match('/^[a-z0-9_-]{1,32}$/i', $value) ? $value : $fallback;
    }

    /**
     * @param  array<int, string>  $allowedHostSuffixes  Empty means any host.
     */
    private function sanitizeUrl($value, array $allowedHostSuffixes = []): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || mb_strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if ($allowedHostSuffixes !== []) {
            foreach ($allowedHostSuffixes as $suffix) {
                if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                    return $url;
                }
            }

            return null;
        }

        return $url;
    }

    private function createVideoPreviewGif(string $videoPath): ?string
    {
        $disk = Storage::disk('public');
        $framePath = 'stories/video-frames/'.Str::uuid().'.gif';
        $inputPath = $disk->path($videoPath);
        $outputPath = $disk->path($framePath);

        $disk->makeDirectory('stories/video-frames');

        try {
            $process = new Process([
                env('FFMPEG_BINARY', 'ffmpeg'),
                '-y',
                '-i',
                $inputPath,
                '-t',
                '3',
                '-vf',
                'fps=10,scale=480:-1:flags=lanczos',
                '-loop',
                '0',
                $outputPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! $disk->exists($framePath)) {
                Log::warning('Unable to create preview GIF for story video', [
                    'video_path' => $videoPath,
                    'error' => trim($process->getErrorOutput()),
                ]);

                return null;
            }

            return Storage::url($framePath);
        } catch (\Throwable $exception) {
            Log::warning('Error creating preview GIF for story video', [
                'video_path' => $videoPath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isBlockedEitherWay(int $userIdA, int $userIdB): bool
    {
        return UserBlock::where(function ($q) use ($userIdA, $userIdB) {
            $q->where('user_id', $userIdA)->where('blocked_user_id', $userIdB);
        })->orWhere(function ($q) use ($userIdA, $userIdB) {
            $q->where('user_id', $userIdB)->where('blocked_user_id', $userIdA);
        })->exists();
    }

    /**
     * Display the specified story.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function show(Request $request, Story $story)
    {
        if ($story->hasExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story has expired',
                ], 404);
            }

            return back()->with('error', 'Story has expired');
        }

        // The index() feed already hides a blocked/blocking user's stories,
        // but that only stops them appearing in the bubble list - opening
        // one directly by ID (e.g. from a stale bubble, deep link, or the
        // viewer paging through) had no such check at all.
        if ($request->user() && $story->user_id !== $request->user()->id
            && $this->isBlockedEitherWay($request->user()->id, $story->user_id)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story not found',
                ], 404);
            }

            return back()->with('error', 'Story not found');
        }

        $story->load(['user', 'viewers', 'reactions']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $story,
            ]);
        }

        // For web requests, redirect to home with story parameter
        return redirect()->route('home', ['story' => $story->id]);
    }

    /**
     * Remove the specified story from storage.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Story $story)
    {
        if ($story->user_id !== Auth::id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            return back()->with('error', 'Unauthorized');
        }

        // Delete associated media file if exists
        if ($story->media_url) {
            $filePath = str_replace('/storage/', '', $story->media_url);
            Storage::disk('public')->delete($filePath);
        }

        if ($story->video_first_frame_url) {
            $framePath = str_replace('/storage/', '', $story->video_first_frame_url);
            Storage::disk('public')->delete($framePath);
        }

        // Mark this as an explicit user deletion (as opposed to the
        // stories:cleanup-expired command's soft-delete of merely-expired
        // stories) so getArchive() can tell the two apart and only ever
        // resurface the latter.
        $story->deleted_by_user = true;
        $story->save();

        $story->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Story deleted successfully',
            ]);
        }

        return back();
    }

    /**
     * Mark a story as viewed by the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function markAsViewed(Request $request, Story $story)
    {
        if ($story->hasExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story has expired',
                ], 404);
            }

            return back()->with('error', 'Story has expired');
        }

        if ($story->user_id !== Auth::id()) {
            if ($this->isBlockedEitherWay(Auth::id(), $story->user_id)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Story not found',
                    ], 404);
                }

                return back()->with('error', 'Story not found');
            }

            StoryViewer::firstOrCreate([
                'story_id' => $story->id,
                'user_id' => Auth::id(),
            ], [
                'viewed_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Story marked as viewed',
            ]);
        }

        return back();
    }

    /**
     * Add or update a reaction to a story.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function react(Request $request, Story $story)
    {
        $validator = Validator::make($request->all(), [
            'reaction_type' => 'required|in:like,love,haha,wow,sad,angry',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator);
        }

        if ($story->hasExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story has expired',
                ], 404);
            }

            return back()->with('error', 'Story has expired');
        }

        if ($story->user_id !== Auth::id() && $this->isBlockedEitherWay(Auth::id(), $story->user_id)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story not found',
                ], 404);
            }

            return back()->with('error', 'Story not found');
        }

        $reaction = StoryReaction::updateOrCreate(
            [
                'story_id' => $story->id,
                'user_id' => Auth::id(),
            ],
            [
                'reaction_type' => $request->reaction_type,
            ]
        );

        // Create notification for story reaction
        // Only notify if user is reacting to someone else's story
        if ($story->user_id !== Auth::id()) {
            NotificationService::createStoryReactionNotification($story, Auth::id(), $request->reaction_type);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $reaction,
            ]);
        }

        return back();
    }

    /**
     * Remove a reaction from a story.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function removeReaction(Request $request, Story $story)
    {
        $reaction = StoryReaction::where([
            'story_id' => $story->id,
            'user_id' => Auth::id(),
        ])->first();

        if ($reaction) {
            $reaction->delete();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Reaction removed successfully',
            ]);
        }

        return back();
    }

    /**
     * Reply to a story by sending a message to the story owner.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function reply(Request $request, Story $story)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator);
        }

        if ($story->hasExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story has expired',
                ], 404);
            }

            return back()->with('error', 'Story has expired');
        }

        $user = Auth::user();
        $storyOwnerId = $story->user_id;

        // Don't allow replying to your own story
        if ($user->id === $storyOwnerId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot reply to your own story',
                ], 400);
            }

            return back()->with('error', 'Cannot reply to your own story');
        }

        if ($this->isBlockedEitherWay($user->id, $storyOwnerId)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Story not found',
                ], 404);
            }

            return back()->with('error', 'Story not found');
        }

        // Load story owner information
        $story->load('user.profile');
        $storyOwner = $story->user;
        $storyOwnerName = $storyOwner->profile->profile_name ?? $storyOwner->username;

        // Find or create conversation between current user and story owner
        $conversation = Conversation::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereHas('participants', function ($query) use ($storyOwnerId) {
            $query->where('user_id', $storyOwnerId);
        })->where('type', 'private')->first();

        if (! $conversation) {
            // Create new conversation
            $conversation = Conversation::create(['type' => 'private']);
            $conversation->participants()->attach([$user->id, $storyOwnerId]);
        }

        // Create message with story reference in content
        $messageContent = $request->content;

        // Create the message with metadata for story reply
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $messageContent,
            'type' => 'text',
            'metadata' => [
                'story_reply' => true,
                'story_id' => $story->id,
                'story_owner_id' => $storyOwnerId,
                'story_owner_name' => $storyOwnerName,
                'story_owner_username' => $storyOwner->username,
            ],
        ]);

        // Update conversation's updated_at timestamp
        $conversation->touch();

        // Load relationships for the response
        $message->load('user.profile');

        // Prepare message data for broadcasting (similar to ChatController)
        $senderData = [
            'id' => $message->user->id,
            'username' => $message->user->username ?? 'Ẩn danh',
            'profile_name' => ($message->user->profile->profile_name ?? null) ?? $message->user->username ?? 'Ẩn danh',
            'avatar_url' => $message->user->avatarUrl(),
        ];

        $messageData = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type,
            'file_url' => $message->file_url ? Storage::url($message->file_url) : null,
            'is_edited' => $message->is_edited,
            'is_myself' => false,  // For the recipient, this is not their own message
            'sender' => $senderData,
            'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
            'created_at_human' => $message->created_at->diffForHumans(),
            'read_at' => $message->read_at?->toISOString(),
            'metadata' => $message->metadata,  // Include metadata for story reply
        ];

        // Broadcast the message to other participants
        broadcast(new MessageSent($conversation->id, $messageData))->toOthers();

        // Create notification for story reply (after message is created and broadcasted)
        NotificationService::createStoryReplyNotification($story, $message, Auth::id());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'content' => $message->content,
                        'type' => $message->type,
                        'conversation_id' => $conversation->id,
                        'metadata' => $message->metadata,
                        'sender' => [
                            'id' => $message->user->id,
                            'username' => $message->user->username,
                            'profile_name' => $message->user->profile->profile_name ?? $message->user->username,
                            'avatar_url' => $message->user->avatarUrl(),
                        ],
                        'created_at' => $message->created_at ? $message->created_at->toISOString() : null,
                    ],
                    'conversation_id' => $conversation->id,
                ],
            ], 201);
        }

        return back();
    }

    /**
     * Get list of viewers for a story.
     * Only the story owner can view the list of viewers.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function getViewers(Request $request, Story $story)
    {
        // Only story owner can view the list of viewers
        if ($story->user_id !== Auth::id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            return back()->with('error', 'Unauthorized');
        }

        // Get all viewers (excluding current user)
        $viewersData = StoryViewer::where('story_id', $story->id)
            ->where('user_id', '!=', Auth::id())
            ->with(['user.profile'])
            ->orderBy('viewed_at', 'desc')
            ->get();

        // Get all reactions for the story
        $reactionsData = StoryReaction::where('story_id', $story->id)
            ->with(['user.profile'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group reactions by user_id
        $reactionsByUser = $reactionsData->groupBy('user_id');

        // Merge viewers with their reactions
        $viewers = $viewersData->map(function ($viewer) use ($reactionsByUser) {
            $userReactions = $reactionsByUser->get($viewer->user_id, collect());

            return [
                'id' => $viewer->user->id,
                'username' => $viewer->user->username,
                'profile_name' => $viewer->user->profile->profile_name ?? $viewer->user->username,
                'profile_picture' => $viewer->user->avatarUrl(),
                'viewed_at' => $viewer->viewed_at ? $viewer->viewed_at->toISOString() : null,
                'viewed_at_human' => $viewer->viewed_at ? $viewer->viewed_at->diffForHumans() : null,
                'reactions' => $userReactions->map(function ($reaction) {
                    return [
                        'type' => $reaction->reaction_type,
                        'created_at' => $reaction->created_at ? $reaction->created_at->toISOString() : null,
                    ];
                })->toArray(),
            ];
        });

        // Also include users who only reacted but didn't view (if any)
        $reactionOnlyUsers = $reactionsData
            ->filter(function ($reaction) use ($viewersData) {
                return ! $viewersData->contains('user_id', $reaction->user_id);
            })
            ->groupBy('user_id')
            ->map(function ($userReactions, $userId) {
                $firstReaction = $userReactions->first();
                $user = $firstReaction->user;

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile_name' => $user->profile->profile_name ?? $user->username,
                    'profile_picture' => $user->avatarUrl(),
                    'viewed_at' => null,
                    'viewed_at_human' => null,
                    'reactions' => $userReactions->map(function ($reaction) {
                        return [
                            'type' => $reaction->reaction_type,
                            'created_at' => $reaction->created_at ? $reaction->created_at->toISOString() : null,
                        ];
                    })->toArray(),
                ];
            });

        // Merge both lists
        $allViewers = $viewers->merge($reactionOnlyUsers)->values();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'story_id' => $story->id,
                    'viewers_count' => $allViewers->count(),
                    'viewers' => $allViewers,
                ],
            ]);
        }

        return back();
    }

    /**
     * Get archived stories for the authenticated user.
     * Returns all stories (active and expired) created by the user.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function getArchive(Request $request)
    {
        $stories = Story::withTrashed()
            ->where('user_id', Auth::id())
            ->where('deleted_by_user', false)
            ->with(['viewers', 'reactions'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($story) {
                return [
                    'id' => $story->id,
                    'media_url' => $story->media_url,
                    'video_first_frame_url' => $story->video_first_frame_url,
                    'text_content' => $story->content,
                    'type' => $story->media_type,
                    'background_color' => $story->background_color,
                    'font_style' => $story->font_style,
                    'text_position' => $story->text_position,
                    'overlays' => $story->overlays,
                    'music' => $story->music,
                    'is_muted' => $story->is_muted,
                    'created_at' => $story->created_at ? $story->created_at->toISOString() : null,
                    'created_at_human' => $story->created_at->diffForHumans(),
                    'expires_at' => $story->expires_at ? $story->expires_at->toISOString() : null,
                    'is_expired' => $story->hasExpired(),
                    'viewers_count' => $story->viewers->where('user_id', '!=', $story->user_id)->count(),
                    'reactions_count' => $story->reactions->count(),
                ];
            })
            ->groupBy(function ($story) {
                // Group by date (YYYY-MM-DD)
                return Carbon::parse($story['created_at'])->format('Y-m-d');
            })
            ->map(function ($stories, $date) {
                return [
                    'date' => $date,
                    'date_human' => Carbon::parse($date)->format('d/m/Y'),
                    'stories' => $stories,
                ];
            })
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $stories,
            ]);
        }

        return back();
    }
}
