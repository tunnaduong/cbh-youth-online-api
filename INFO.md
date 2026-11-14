# INFO — CBH Youth Online — API

> **For AI agents.** Project map for agents working in this repo: how it connects to the sibling repos, features, structure, setup, conventions and recent work. Humans: see `README.md`. Keep this file current - add to **Recent work** and update other sections whenever you change the repo.

- **Default branch: `main`** - work, commit and push there unless the user names another branch.
- **Find code via this file first**: check the project structure / features sections below before grepping the repo by hand.

Laravel 10 backend behind **https://api.chuyenbienhoa.com** for CBH Youth Online (Diễn đàn học sinh Chuyên Biên Hòa — THPT Chuyên Biên Hòa, Hà Nam). One JSON API serves every client.

> Deeper docs (read these instead of re-deriving): [POINTS_SYSTEM_README.md](POINTS_SYSTEM_README.md) (points/ranking), [PROFILE_THEME_SPEC.md](PROFILE_THEME_SPEC.md) (Discord-style profile customization), [PERFORMANCE_OPTIMIZATION_GUIDE.md](PERFORMANCE_OPTIMIZATION_GUIDE.md), [endpoint.txt](endpoint.txt) (full `route:list` dump dated 30/7/2026 — may lag `routes/api.php`).

## The four repos

| Repo (local path) | What | Talks to this API via |
|---|---|---|
| **cbh-youth-online-api** (this) | Laravel API, Filament admin, queues, websockets | — |
| [cbh-youth-online-next-js](https://github.com/tunnaduong/cbh-youth-online-next-js) | Web app **chuyenbienhoa.com** (Next.js), incl. `/admin` | axios, `Authorization: Bearer <token>`; token kept in the shared `auth_token` cookie |
| [cbh-youth-online-mobile](https://github.com/tunnaduong/cbh-youth-online-mobile) | Expo / React Native app | axios, token in AsyncStorage |
| [cbh-youth-online-gift-shop](https://github.com/tunnaduong/cbh-youth-online-gift-shop) | **giftshop.chuyenbienhoa.com** (Next.js 16) | `fetch`; reads the shared `.chuyenbienhoa.com` `auth_token` cookie |

- **All client routes are under `/v1.0`** (`routes/api.php`, `Route::prefix('v1.0')`). `routes/web.php` is a legacy Inertia/Blade site plus webhooks; clients don't use it.
- **Auth = Laravel Sanctum personal access tokens** (`$user->createToken(...)`), sent as bearer tokens. Tokens don't expire (`sanctum.expiration` is null). The user model is `App\Models\AuthAccount` (with `UserProfile`).
- **Realtime = Laravel Reverb** (Pusher protocol; clients use laravel-echo + pusher-js). Channels in `routes/channels.php`: private `App.Models.User.{id}`, presence `chat.{conversationId}`. Events in `app/Events` (MessageSent/Edited/Deleted/Recalled/Reacted/Read, AiTyping). Clients send `X-Socket-Id` so `broadcast()->toOthers()` skips the sender.
- **Push**: Expo push tokens (mobile) and Web Push/VAPID (web), via `PushNotificationService` / `NotificationService`; iOS VoIP (PushKit) tokens for incoming calls go straight to APNs (`ApnsVoipService`).

## Route access tiers (`routes/api.php`)

1. **Public** — login/register/OAuth/password reset, avatars/covers, file serving, gift-shop catalog, SePay webhook, VAPID key, feedback (throttled), web-session redeem.
2. **`optional.auth`** — works for guests, adds viewer-specific data when a token is sent (forum/topics, profiles, search, stories, study materials, games, public chat, group invite preview). Content from users blocked in either direction is filtered out.
3. **`auth:sanctum` + `not_banned` + `device_session`** — everything that writes or is personal (`device_session` keeps the token's device details current).
4. **`role:admin`** (`CheckRole` middleware; **teachers pass it too** - `teacher` has the same rights as `admin`, see `AuthAccount::STAFF_ROLES`) — moderation, report admin, broadcasts, deposit/withdrawal approval, student verifications, and `/v1.0/admin/*` (`Admin\AdminPanelController`), which the web `/admin` uses.

Middleware aliases live in `app/Http/Kernel.php` (`optional.auth`, `not_banned`, `role`, `device_session`).

## Features (domain → main code)

- **Auth & accounts** — `AuthController`: username/password login, register with email verification codes, Google/Facebook (Socialite + PKCE code exchange `POST /oauth/exchange`), Apple (`AppleIdTokenVerifier`), logout (revokes the current token), ban checks (`bannedResponse`), **web-session handoff** (mobile → web: `POST /web-session/handoff` gives a 60-second code, `POST /web-session/redeem` turns it into a separate token named `web-handoff:<app token id>`), **two-factor login** (email code or authenticator app, recovery codes, remembered devices; `TwoFactorController`, `TwoFactorService`, `TotpService`), **logged-in devices** (`DeviceSessionController`) and a new-device login email (`DeviceSessionService`). Password reset: `ForgotPasswordController`, `PasswordResetController`.
- **Users & profiles** — `UserController`, `ProfileController`, `FollowController`, `UserBlockController` (2-way block hides everything; blocked profile returns 404), profile photo gallery, liked-posts list (`GET /users/{username}/likes`), online status (`OnlineUserController`, `ActivityController`), account deletion.
- **Profile customization** — `ProfileThemeService`: avatar frames, name effects, profile effects/frames unlocked by points milestones, and from Pro Plus (2250) an uploaded image as avatar / profile frame (`CustomFrameService`, `CustomFrameController`); returned on authors of posts, comments, chat, rankings and notifications. Spec: PROFILE_THEME_SPEC.md.
- **Forum** — `ForumController`, `TopicsController`: main categories → categories → subforums; topics (posts) with images/video; nested comments (3 levels); votes, views, saved posts (`SavedPostsController`), hidden topics, archive/unarchive, hashtags (`HashtagService`), mentions (`MentionService`), home feed, related posts under a post (`GET /topics/{id}/related`).
- **Moderation** — `ContentModerationService` + `ModerationQueue`, `Admin\ModerationController`; admins are emailed when content enters the queue. Forum photos are checked by a vision model (`ImageModerationService`); anything not clearly fine waits in the queue. User reports: `UserReportController`. School monitoring reports / student violations: `MonitorReport`, `StudentViolation`.
- **Chat** — `ChatController`: 1-1 and group conversations, invite links, reactions, edit/recall/delete, read receipts, attachments (up to ~100MB), chat backgrounds, share-a-post messages with preview, public chat room, **AI chat** (`AiChatService`, `GenerateAiChatReply` job, key `CYO_AI_API`; it can look up the school account's posts, `SchoolKnowledgeService`). Admin access to messages is logged (`AdminMessageAccessLog`).
- **Stories** — `StoryController`: 24h stories with overlays (text, stickers, mentions, links), music, reactions, viewers; `stories:cleanup-expired` runs hourly.
- **Points & wallet** — `PointsService`, `PointsController` (gift points `POST /points/gift`), `DailyCheckinController`, `UserPointDeductionController`, `WalletController`; deposits by **SePay** bank transfer (`PendingDeposit`, `SEPayWebhookController` / `SEPayWebhookService`), withdrawals (`WithdrawalRequest`, admin approve/reject). See POINTS_SYSTEM_README.md.
- **Gift shop** — `ShopController` + `ShopProduct/ShopProductVariant/ShopCategory/ShopOrder/ShopOrderItem`: public catalog, orders (points / QR / COD, optional map pin → `maps_url`), cancelling until an order ships (points refunded, transfers flagged for refund), shop support chat with an AI assistant that can show product photos, draw up an order slip the customer confirms (`POST /shop/support/messages/{id}/order`) and resend the QR to pay; also managed in Filament (`app/Filament/Resources`).
- **Student verification** — `StudentVerificationController` (student KYC; verified students get a shop discount).
- **Study materials** — `StudyMaterialController`, categories, ratings, purchases with points, preview generation (`GenerateStudyMaterialPreview`, pdfparser/phpword).
- **Document printing** — `PrintRequestController`, `Admin\PrintRequestController`: users submit documents to print with file, page count, delivery method (class / office); staff approve, assign, track printing and delivery status; full state machine with timestamps.
- **Quiz & games** — `QuizController` (AI-generated questions via Gemini keys `GEMINI_1..5`, `QuizGenerationService`; online only - no question bank, an AI failure returns 503), `CustomQuizController` (quizzes parsed from user documents), `GameController` (play sessions, leaderboard).
- **Notifications** — `NotificationController`, `NotificationSettingsController`, Expo token register/unregister, web push subscribe; weekly newsletter (`newsletter:send-weekly`, Monday 08:00) with unsubscribe.
- **Misc** — `SearchController`, `UniversityController` (proxies Cốc Cốc học tập), `ExamScoreController` (exam score lookup proxies, see Recent work), `TimetableController` (class timetable per account, `cyo_timetables`), `YouthNewsController`, `HelpCenterController`, `FeedbackController` (in-app bug reports/suggestions, guests allowed), `RecordingController`, `FileUploadController` (`/upload`, `/user-content/{id}`; image/video compression jobs), `FacebookWebhookController`.
- **Admin** — Filament panel at `/admin` on this host (shop resources), plus the JSON admin API for the Next.js `/admin` (`Admin\*`, `AdminController`, `AdminBroadcast` + `SendAdminBroadcast` job). Staff can also create accounts by hand (`POST /admin/users`, email `<username>@tunnaduong.com`).
- **API docs** — `dedoc/scramble` (`config/scramble.php`).

## Project structure

```
app/
  Console/Commands/    artisan maintenance & one-off commands (backfills, compression, emails, points, cleanup)
  Console/Kernel.php   scheduler (weekly newsletter, hourly story cleanup)
  Events/              chat broadcast events (Reverb)
  Filament/Resources/  Filament admin resources (shop)
  Http/Controllers/    API controllers, one per domain; Admin/ = admin panel API
  Http/Middleware/     optional.auth, not_banned, role (CheckRole), …
  Jobs/                queued work: AI replies, media compression, previews, broadcasts
  Mail/, Notifications/  emails & notifications
  Models/              Eloquent models (AuthAccount = user)
  Services/            domain logic (points, moderation, push, quiz, AI, SePay, profile themes, …)
config/                incl. forum.php, sepay.php, reverb.php, octane.php, scramble.php, services.php (OAuth, VAPID, AI, Gemini keys)
database/              migrations (~170), factories, seeders
routes/api.php         ALL client endpoints (/v1.0)
routes/web.php         legacy Inertia site + webhooks; channels.php = broadcast auth
resources/             Blade views (emails, legacy app shell), lang
api/index.php          Vercel PHP entry (just requires public/index.php)
deploy/                nginx upload-size snippet, supervisor queue-worker config, game-proxy supervisor + nginx
  game-proxy/            Go game proxy (cookie/save sync, ad blocking); see its README
scripts/setup-cron.sh  cron for points refresh (points:refresh-all every 30 min)
tests/                 PHPUnit (Feature: auth, profile, voting; Unit: ProfileThemeService, CustomFrameService, SchoolKnowledgeService, story overlay sanitizer, TotpService)
.rr.yaml, rr           RoadRunner binary + config (Laravel Octane)
```

## Setup & run

> `php` is **not installed** in the current dev container (Termux/PRoot): you can edit code but can't run artisan or tests here. Verify on a machine with PHP.

Requirements: PHP ^8.1, Composer, MySQL, Node (only for the legacy Vite/Inertia assets), optionally Redis (predis).

```bash
composer install
npm install                 # also runs patch-package
cp .env.example .env && php artisan key:generate
# fill DB_*, MAIL_*, REVERB_*, WEBRTC_TURN_SERVER + WEBRTC_TURN_SECRET (coturn, for calls), APNS_KEY_ID / APNS_TEAM_ID / APNS_PRIVATE_KEY_PATH (iOS CallKit call pushes), GOOGLE_*/FACEBOOK_*/APPLE_CLIENT_ID, VAPID_*, CYO_AI_API, IMAGE_MODERATION_API_KEY, GEMINI_1..5, SePay, RECAPTCHA_*; optional FORUM_SCHOOL_ACCOUNT (default DoanTruongCBH)
php artisan migrate
php artisan storage:link
php artisan serve           # or: php artisan octane:start (RoadRunner)
php artisan reverb:start    # websockets
php artisan queue:work      # QUEUE_CONNECTION=database: media compression, AI replies, broadcasts
php artisan schedule:work   # or cron: * * * * * php artisan schedule:run
php artisan test            # PHPUnit (phpunit.xml)
./vendor/bin/pint           # code style
```

Cache note: the web-session handoff, two-factor login challenges and emailed codes (and other features) store state in `Cache`, so the production `CACHE_DRIVER` must persist across requests (`file`/`redis`, never `array`).

## Deploy

- **Production**: aaPanel server at `/www/wwwroot/api.chuyenbienhoa.com`, nginx + PHP-FPM. Apply `deploy/nginx-upload-size.conf` (105MB bodies, 600s timeouts) and `deploy/supervisor-queue-worker.conf` (2 queue workers). Run `php artisan migrate` after pulling.
- `vercel.json` (vercel-php → `api/index.php`) is an alternative serverless target. It sets `CACHE_DRIVER=array`, which breaks Cache-based features such as the web-session handoff.

## Conventions

- 2-space indentation in PHP; comments explain *why*, not *what*.
- User-facing messages (validation, errors) are **Vietnamese**. Commit messages are Vietnamese or English, conventional style (`feat(scope): …`, `fix(scope): …`).
- `main` is pushed to directly (not protected); larger features sometimes go through PRs.
- New client endpoints go in `routes/api.php` under the right access tier. Keep response shapes stable: web, mobile and gift shop all consume them.

## Recent work (newest first)

- **Game proxy (branch `feat/game-proxy-sync`, local - not pushed, not run: no Go toolchain / DB on the dev machine, PHP-linted only):** a small Go server (`game-proxy/`, stdlib only) streams a third-party game through our own origin so an account's **cookies + localStorage sync across devices** and **ad hosts are blocked**. Laravel: `POST /v1.0/games/{slug}/proxy-link` (signed play link, `GameProxyToken`), `GET|DELETE /games/{slug}/save`, internal `GET|PUT /internal/game-proxy/saves/{userId}/{slug}` (header `X-Game-Proxy-Key`, `GameProxyKey` middleware), `cyo_game_saves` + `cyo_games.use_proxy`, `config/gameproxy.php` (`GAME_PROXY_*`), `game-proxy:ensure` (scheduler, every minute: health-check + detached start, so no Supervisor needed) and `game-proxy:start` (foreground, optional Supervisor: `deploy/supervisor-game-proxy.conf`). `GET /games/{slug}` now returns `use_proxy`. **Needs a separate domain** (not a chuyenbienhoa.com subdomain: the login cookie would leak to game scripts) + `deploy/nginx-game-proxy.conf`; build `game-proxy/bin/game-proxy` (README). Run `php artisan migrate`.
- **Timetable "Nhập bằng AI"** (`TimetableAiService`, `POST /v1.0/timetable/ai-import`, multipart `text` / `image` (JPG/PNG/WebP ≤ 8 MB) / `current`; tests `TimetableAiServiceTest` with faked replies - **no real model call was made**, no key on the dev machine): free text like "Sáng tiết 1 7h30 đến 8h15, ra chơi 15p..." or a photo → `{data, note}`, not saved (the client shows it in the editor, user saves with PUT). Model = the photo-moderation vision endpoint (`IMAGE_MODERATION_*`), then `GEMINI_1..5`; neither set → 424. Reply goes through `fixAfternoon()` (1:45 → 13:45) and `normalize()`. Limits: 10/min, 30/day per account. All model calls share a 75 s budget (≤45 s each; Cloudflare cuts at 100 s, and a cut request reaches the app as HTML, shown as "Máy chủ đang gặp sự cố"), `set_time_limit(100)` against PHP-FPM's 30 s, a 400 from the gateway retries without `response_format`, any failure answers 424 JSON. Timings and model errors are logged (`grep TimetableAi storage/logs/laravel.log`).

- **Class timetable ("Thời khóa biểu")** (`TimetableController`, `Timetable` model, `resources/data/timetable_demo.json`; unit test `TimetableNormalizeTest`; **run `php artisan migrate`** for `cyo_timetables`): one schedule per account so web and app share it. `GET /v1.0/timetable/demo` (public), `GET|PUT|DELETE /v1.0/timetable` (auth; GET falls back to the demo with `is_demo: true`, DELETE resets). Format = vaolop.app's JSON (`T2`..`T7`, `CN` → blocks `{type: hoc|nghi, start, end, subject|label}`), so its exports import as is. PUT cleans it (HH:MM, end > start, subject required, ≤30 blocks/day, sorted) or answers 422 naming the day and row. The clients compute the current / next period.

- **Exam score lookup proxies** (`ExamScoreController`, `/v1.0/exam-scores/*`, throttle 30/min; tried locally against the live sites, no successful lookup - no real SBD at hand; no migration): **THPT (tốt nghiệp)** relays tracuudiemnb.ninhbinh.edu.vn - `GET {source}/config`, `GET {source}/captcha` → `{id, svg}` (SVG text, `dominant-baseline` rewritten for react-native-svg), source down = 424 (not 5xx, so the web does not show its maintenance screen), `POST {source}/search {so_bao_danh, captcha_id, captcha_answer}` → `{diem_thi, subjects[], ghi_chu}`, 422 with `reason` `captcha`/`not_found`. That site is HTTP-only (expired cert), hence the proxy. **Thi vào 10**: `GET ninh-binh-10/search?sbd=` relays diemthininhbinh-ten.vercel.app (no captcha; school = first 2 SBD digits) and adds `school_name`, `chi_tieu`, `chance` (`likely`/`borderline`/`unlikely`); school/quota tables are hard-coded from that site (2026) - update yearly.

- **Incoming calls use the phone's own call screen** (`ApnsVoipService`, `VoipPushToken`, `PushNotificationService::sendExpoCallPushNotification` / `sendCallCancelledPush`; `php -l` + a JWT-signing check only, not sent to a real device; **run `php artisan migrate`** for `cyo_voip_push_tokens`): iOS devices register a PushKit token (`POST/DELETE /v1.0/notifications/voip/{register,unregister}`) and a call is sent to them as a VoIP push straight to APNs (HTTP/2, ES256 provider token cached 50 min, production host first, sandbox on `BadDeviceToken`, token deactivated on 410) - the app reports it to CallKit; those iOS devices then skip the Expo call notification. **Needs `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_PRIVATE_KEY_PATH` (or `APNS_PRIVATE_KEY`) in `.env`, then `php artisan config:cache`**; without them nothing changes on iOS. Android keeps the Expo push (the app turns it into a full-screen call); the call push data now carries a slim `call` (no profile theme) and `conversation_name`. When the caller hangs up before an answer, callees get a `call_cancelled` push ("Cuộc gọi nhỡ") that also stops the Android ringing.

- **Cloudflare Realtime TURN for calls** (`CallController::cloudflareTurnServers`, `config/services.php` `webrtc`; `php -l` and a run against a fake response only - not tried with a real key; no migration): with `CLOUDFLARE_TURN_KEY_ID` + `CLOUDFLARE_TURN_API_TOKEN` in `.env`, the ICE server list gains Cloudflare's TURN addresses with a login valid for a day (`POST rtc.live.cloudflare.com/v1/turn/keys/{id}/credentials/generate-ice-servers`), cached an hour; a failed request is cached a minute and calls go on with STUN only. Port-53 addresses are dropped (browsers block them). Meant for this server, which sits at home behind a Cloudflare Tunnel: the tunnel carries HTTP only, so a coturn on the same machine would need ports forwarded on the router. Can be combined with `WEBRTC_TURN_SERVER`.

- **TURN settings are read from config; expiring TURN logins** (`config/services.php` `webrtc`, `CallController::getIceServersList`; `php -l` only; no migration): the controller read `WEBRTC_TURN_*` with `env()`, which returns null once `php artisan config:cache` has run - so a TURN server set in `.env` was never sent to clients. They are in `config('services.webrtc.*')` now. New `WEBRTC_TURN_SECRET` (coturn `use-auth-secret` / `static-auth-secret`): each client gets `username = <expiry>:<user id>`, `credential = base64(HMAC-SHA1(secret, username))`, valid `WEBRTC_TURN_TTL` seconds (default 86400); the fixed `WEBRTC_TURN_USERNAME` / `WEBRTC_TURN_PASSWORD` pair still works when no secret is set. **After editing `.env`: `php artisan config:cache`.**

- **Calls: relay audio chunks carry their sequence number; several TURN addresses** (`app/Http/Controllers/CallController.php`; `php -l` only; no migration): the fallback `audio_chunk` broadcast now has `aseq` inside its payload (it was only in the HTTP poll's copy), so web and mobile play a chunk once although it arrives on two socket channels and in the poll. `WEBRTC_TURN_SERVER` may hold several addresses separated by commas. **Voice only stays clear when the two sides connect directly or through TURN**: without `WEBRTC_TURN_SERVER` / `WEBRTC_TURN_USERNAME` / `WEBRTC_TURN_PASSWORD` in `.env` (no TURN server is built in - only STUN), a call between networks that block direct connections runs on the HTTP relay (8 kHz, delayed).

- **Markdown tables become `<table>`** (`app/Http/Controllers/TopicsController.php`, `TableExtension`; `php -l` only; no migration): `convertMarkdownToHtml` had no table support, so a `| a | b |` table was stored as one paragraph with pipes. Posts written before this keep their old `content_html`; saving them again (edit) re-renders it. The mobile app draws the grid with `components/MarkdownTable.js`.

- **`LearningTipsTopicSeeder` can be run again safely** (seeded on the server; no migration): it skips a topic that the school account already has under the same title, so re-running it after a failed run no longer makes duplicates. The failed runs came from `storage/logs/laravel.log` being owned by `www` (775) while artisan ran as `pi`: any `Log::` call (here the points log in `Topic` creation) threw `UnexpectedValueException ... Permission denied`. **Run artisan as the web user** (`sudo -u www php artisan ...`), or add the shell user to the `www` group.

- **Fix: a new post showed raw markdown in the feed right after posting** (`app/Http/Controllers/TopicsController.php`; `php -l` only; no migration): `POST /v1.0/topics` returned `content` = the raw markdown `description`, and the mobile app puts that response straight into the feed as the new post, whose card renders `content` as HTML - so `##`, `**` and `[..](..)` appeared as plain text on one line until the feed was refreshed. It now returns `content_html` (falling back to the markdown), the same shape the feed uses. The web composer does not read `content` from this response. Checked that `convertMarkdownToHtml` renders the guide file correctly (headings, lists, `<hr />`).

- **Print requests: admin file endpoint, search fix, file_id table fix** (`php -l` only; no migration): `GET /v1.0/admin/print-requests/{id}/file` (`Admin\PrintRequestController::file`, `role:admin`) serves the uploaded document inline through Laravel (so it has the API's CORS headers) - the web admin preview fetches it as a blob and prints it from a same-origin frame. 404 when the file row or the file on the `public` disk is missing. The admin list's search is now grouped, so `orWhere('title')` no longer escapes the status filter. `POST /print-requests` validated `file_id` against a table that does not exist (`cyo_user_contents`); it is `cyo_cdn_user_content` (`UserContent`).

- **Document Printing Request System** (`app/Models/PrintRequest.php`, `app/Http/Controllers/PrintRequestController.php`, `app/Http/Controllers/Admin/PrintRequestController.php`, `routes/api.php`, `database/migrations/2026_11_12_000001_create_cyo_print_requests_table.php`):
  - **User endpoints** (`auth:sanctum`): `GET /v1.0/print-requests` (list), `POST /v1.0/print-requests` (submit), `GET /v1.0/print-requests/{id}` (view), `PUT /v1.0/print-requests/{id}` (update pending requests), `POST /v1.0/print-requests/{id}/cancel` (cancel).
  - **Admin endpoints** (`role:admin`): `GET /v1.0/admin/print-requests` (list with filters), `GET /v1.0/admin/print-requests/{id}` (view details), `PUT /v1.0/admin/print-requests/{id}/status` (update status with timestamps), `POST /v1.0/admin/print-requests/{id}/assign` (assign staff member), `DELETE /v1.0/admin/print-requests/{id}` (delete).
  - **Status workflow**: pending → approved/rejected/cancelled; approved → printing/cancelled; printing → ready/cancelled; ready → delivered/picked_up/cancelled; terminal states: delivered, picked_up, cancelled, rejected.
  - **Database schema**: tracks user_id, assigned_staff_id, file_id (links to cyo_user_content), delivery_method (delivery_to_class / pickup_at_office), pages_count, class_or_location, status, and timestamps for each state transition.

- **Post Translation by Yoyo AI with Multi-Key Gemini Fallback & Server-Side Caching** (`app/Services/AiChatService.php`, `app/Http/Controllers/TopicsController.php`, `routes/api.php`):
  - Added `translatePost(?string $title, ?string $description, string $targetLang): array` in `AiChatService.php` instructing Yoyo AI to translate forum post titles and markdown content while preserving 100% of markdown structure (headings, links, lists, code blocks, emojis, @mentions).
  - Resilient AI execution in `AiChatService::requestRaw()`: supports primary CYO_AI_API proxy with automatic failover to Google AI Studio Gemini API (`GEMINI_1..5` keys) and JSON enforcement (`responseMimeType => application/json` / `response_format => json_object`), with regex fallback parser preventing unescaped multiline markdown JSON breaks.
  - Handles nullable/empty descriptions, image-only/title-only posts, and extracts text from `content_html` when markdown description is absent.
  - Supports 4 languages: `vi` (Vietnamese), `en` (English), `ru` (Russian), and `zh` (Simplified Chinese).
  - Registered endpoint `POST /v1.0/topics/{id}/translate` in `routes/api.php` under `optional.auth` middleware.
  - Caches translation results in Laravel Cache (`topic_trans:{id}:{lang}:{updated_at_timestamp}`) for 30 days to eliminate duplicate AI calls. Any post edit via `TopicsController::update()` invalidates stale cache keys and bumps the timestamp version so translations remain updated.

- **Fix WebRTC Audio Failure: Reverb 10KB Payload Limit (SDP Chunking), `throttle:api` 429 on Trickle ICE, `/calls/ice-servers` Route & HTTP Signal Cache Sync** (`config/reverb.php`, `app/Providers/RouteServiceProvider.php`, `routes/api.php`, `app/Http/Controllers/CallController.php`):
  - **Reverb Payload Size & Automatic SDP Chunking**: Raised default `REVERB_APP_MAX_MESSAGE_SIZE` / `max_request_size` in `config/reverb.php` from `10_000` (10KB) to `100_000` (100KB). Additionally, `CallController::signal()` now automatically splits any WebRTC SDP `offer`/`answer` > 3,800 bytes into 2,800-byte `sdp_chunk` broadcasts so even a running Reverb daemon with the old 10KB limit in memory never throws `HTTP 413 Payload too large` / `BroadcastException` (HTTP 500) on SDP offers/answers.
  - **Dedicated `throttle:calls` Rate Limiter (1,000 req/min)**: Exempted `/v1.0/chat/calls/*` and `/v1.0/chat/conversations/{id}/call/initiate` from the 60 req/min `throttle:api` limiter (`Route::withoutMiddleware(['throttle:api'])->middleware(['throttle:calls'])`), preventing Trickle ICE candidate bursts (30–50 `POST /calls/{id}/signal` requests in 1s) from failing with **HTTP 429 Too Many Requests**.
  - **Added `GET /v1.0/chat/calls/ice-servers` Route Alias**: Registered `calls/ice-servers` alongside `ice-servers` so mobile clients calling `/v1.0/chat/calls/ice-servers` receive the STUN/TURN server list instead of HTTP 404.
  - **Cached Signals in `GET /v1.0/chat/calls/active` (`?call_id=`)**: Every signal (`ringing`, `offer`, `answer`, `candidate`) is appended to `Cache::put("call_signals:{$call->id}", ..., 15 min)` and returned in `GET /v1.0/chat/calls/active`, providing a 100% reliable HTTP fallback alongside Reverb WebSockets.

- **WebRTC Call Signaling Delivery & TURN Relay Fallback** (`app/Events/CallAnswered.php`, `app/Events/CallSignaled.php`, `app/Events/CallRejected.php`, `app/Events/CallEnded.php`, `app/Http/Controllers/CallController.php`):
  - `CallAnswered`, `CallSignaled`, `CallRejected`, and `CallEnded` now broadcast on both `PresenceChannel('chat.{conversationId}')` and each participant's persistent `PrivateChannel('App.Models.User.{userId}')`, eliminating dropped `ringing`, `offer`, `answer`, and `candidate` packets while clients are still authenticating the conversation presence channel.
  - `CallController::getIceServersList()` now includes Cloudflare STUN (`stun:stun.cloudflare.com:3478`) and OpenRelay TURN fallback servers (UDP/TCP 80 & 443) when `WEBRTC_TURN_SERVER` is not set in environment.

- **User Profile Videos Endpoint (`GET /v1.0/users/{username}/videos`)**:
  - Added `UserController::getUserVideos()` and registered route `GET /v1.0/users/{username}/videos` in `routes/api.php` to fetch paginated videos uploaded in user's visible posts (`cdn_video_id`), matching `getUserPhotos` behavior and visibility rules.


- **Fix SQL error in active call lookup (`GET /v1.0/chat/calls/active`)**:
  - `CallController::activeCall()`: Fixed column reference `auth_account.id` to `cyo_auth_accounts.id` inside the `conversation.participants` subquery constraint. Prevents MySQL error 1054 (`Unknown column 'auth_account.id' in 'where clause'`).

- **WebRTC Voice & Video Call API & Realtime Signaling** (`database/migrations/2026_11_05_000001_create_cyo_calls_table.php`, `app/Models/Call.php`, `app/Http/Controllers/CallController.php`, `app/Events/Call*.php`, `routes/api.php`):
  - **Database & Model**: Added `cyo_calls` table and `Call` Eloquent model supporting 1-1 voice and video call sessions with full lifecycle (`ringing`, `accepted`, `rejected`, `busy`, `ended`, `missed`, `cancelled`), timestamps, and duration calculation.
  - **Endpoints (`/v1.0/chat/...`)**:
    - `POST /conversations/{id}/call/initiate`: checks 2-way blocks and concurrent active calls, initiates call, broadcasts `CallInitiated`.
    - `POST /calls/{id}/answer`: transitions status to `accepted`, marks `accepted_at`, broadcasts `CallAnswered`.
    - `POST /calls/{id}/reject`: marks call rejected/busy, creates missed call message in conversation, broadcasts `CallRejected`.
    - `POST /calls/{id}/end`: ends call, computes call duration, creates call message in conversation (`Cuộc gọi thoại/video kết thúc - XX:XX`), broadcasts `CallEnded`.
    - `POST /calls/{id}/signal`: ultra-low latency WebRTC SDP offer/answer and ICE candidate relay via `CallSignaled`.
    - `GET /ice-servers`: returns default Google STUN servers and optional custom STUN/TURN servers from environment.
  - **Broadcast Events (`ShouldBroadcastNow`)**: `CallInitiated` (dispatched on `App.Models.User.{receiver_id}` and `chat.{conversation_id}`), `CallAnswered`, `CallRejected`, `CallEnded`, and `CallSignaled` (dispatched on `chat.{conversation_id}`).

- **Home feed excludes teachers' posts, keeps admins' posts** (`php -l` clean; unit test in `tests/Unit/TopicFromMembersTest.php`; no migration): `Topic::scopeFromMembers()` now excludes only `role === 'teacher'` (and the youth union account `forum.school_account`), allowing posts by `role === 'admin'` to appear on the homepage (`/v1.0/home`, `/v1.0/topics/feed` in personalized, `mode=latest`, and guest feeds). Feed cache key bumped to `feed_scores_v5_*`.

- **Self-hosted OTA (Over-The-Air) updates via Expo Updates Protocol v1** (`app/Http/Controllers/OtaController.php`, `app/Console/Commands/OtaCommand.php`, `app/Console/Commands/OtaRollbackCommand.php`, `routes/api.php`, `config/services.php`):
  - Added self-hosted OTA updates server compliant with Expo Updates Protocol v1 (`multipart/mixed` responses with directives `noUpdateAvailable` and `rollBackToEmbedded`, manifest with base64url SHA-256 hashes).
  - Endpoints:
    - `GET /v1.0/ota/manifest`: returns manifest or directive based on `expo-platform`, `expo-runtime-version`, `expo-channel-name`, and `expo-current-update-id`. Also mirrored at top-level `/ota/manifest`.
    - `GET /v1.0/ota/assets/{channel}/{runtimeVersion}/{updateId}/{path}`: streams JS bundles (`.js`, `.hbc`) and assets with immutable caching.
    - `POST /v1.0/admin/ota/publish`: accepts `.zip` export of `dist/` (from `npx expo export`), computes base64url SHA-256 hashes, registers manifests, and activates the release. Protected by `OTA_DEPLOY_TOKEN` Bearer token or admin auth.
    - `POST /v1.0/admin/ota/rollback`: instantly rolls back a channel to embedded binary or to a previous `update_id`.
    - `GET /v1.0/admin/ota/history`: returns active release and history log.
  - Artisan CLI commands: `php artisan ota:status` and `php artisan ota:rollback`.

- **Admin can create accounts by hand (branch `claude/sharp-cerf-l3nye3`; tests in `tests/Feature/AdminCreateUserTest.php`):** `POST /v1.0/admin/users` (`AdminPanelController::createUser`, `role:admin`) makes an account for seeding the forum. Fields: `username` (same rules as public sign-up), `name` (profile name), optional `password` (min 6; left out = a random 12-character one, returned once as `password`), optional `avatar` image (cropped to a 156 px square like `UserController::updateAvatar`, HEIC too). The email is always `<username>@tunnaduong.com` and is marked verified, so no mail is sent. Role is the default `user`. Logged as `ADMIN_CREATE_USER`. Used by the Next.js `/admin/users` "Tạo người dùng" dialog.

- **Weekly newsletter links point to the live site** (`php -l` only; no deploy needed beyond the code): `WeeklyNewsletterMail` and the newsletter unsubscribe redirect fell back to `http://localhost:3000` when `APP_UI_URL` was not set in `.env`, so every post button in the Monday email went to localhost. Both now fall back to `https://chuyenbienhoa.com`, like the other public-facing mails. Setting `APP_UI_URL` on the server still overrides it.

- **`GET /v1.0/topics/{id}/related`: posts related to one post** (not run end to end - `php -l` only, no `vendor/` here; no migration): `TopicsController::related()` (optional-auth, guests allowed) returns up to 6 posts in the list shape (`formatTopicForList`) plus `subforum` `{id, name}`. Candidates come from `visibleTopicsQuery()` (same visibility and block rules as the listings), excluding pending / rejected posts and the post itself, and only the 200 newest are scored. Score: same subforum +3, each word of the title (≥ 3 letters, filler words dropped, max 6) found in the candidate's title +2, each shared hashtag +2. A post by someone blocked with the viewer, a hidden / pending / unknown post returns `{posts: []}`. Used by the web post page under the comments.

- **WebRTC Audio Call Reliability: Atomic Signal Cache Locking, Dead TURN Blackhole Removal & WebAudio Relay Fallback** (`app/Http/Controllers/CallController.php`):
  - **Atomic Signal Cache Locking & Pinned Offer/Answer Keys**: Wrapped `signal()` read-modify-write cache operations in `Cache::lock("lock:call_signals:{$call->id}", 5)` and pinned `call_offer:{$call->id}` / `call_answer:{$call->id}` in `getMergedCallSignals()` so concurrent Trickle ICE `candidate` POST requests never race and overwrite SDP offers/answers in cache.
  - **Removed Dead Metered OpenRelay TURN Server**: Removed `openrelay.metered.ca` (`192.0.2.1` RFC 5737 TEST-NET-1 blackhole) from `getIceServersList()`, keeping verified Cloudflare TURN (`turn.cloudflare.com`), ExpressTURN (`free.expressturn.com`), and Google STUN servers.
  - **Real-time 8kHz G.711 μ-law Audio Relay Fallback (`audio_chunk`)**: Added `audio_chunk` signal support with ring-buffer cache `call_audio:{$call->id}` and `CallSignaled` broadcast in `signal()`, `activeCall()`, and `answer()` so voice calls have guaranteed two-way audio even when P2P WebRTC RTP packets are blocked by symmetric NAT/firewalls.

- **OTA Updates: Multi-channel support & directory traversal hardening** (`app/Http/Controllers/OtaController.php`, `tests/Feature/OtaUpdateTest.php`):
  - Confirmed and verified multi-channel OTA support across publish, manifest delivery, and asset streaming (`storage/app/ota/{channel}/{runtimeVersion}`).
  - Added strict parameter sanitization for `channel` (`[a-zA-Z0-9_\-]`) and `runtimeVersion` across `manifest`, `asset`, `publish`, `rollback`, and `history` to ensure directory traversal safety.
  - Added multi-channel isolation feature tests in `OtaUpdateTest.php` ensuring independent updates across `preview` and `production` channels.

- **Yoyo AI: Giftshop clothing size guide and canonical school knowledge reference topic** (no migration):
  - Added full sizing chart (S to 4XL) and sizing advice rules to `AiChatService::SHOP_SUPPORT_PROMPT` so Yoyo AI accurately advises customers on clothing sizes.
  - Added canonical reference topic `379555903` ("Trường THPT Chuyên Biên Hòa: Thông tin tuyển sinh, môi trường học tập và cộng đồng học sinh") to `SchoolKnowledgeService` and `AiChatService` prompts so queries regarding school admissions, environment, and community reference this comprehensive article regardless of author.
  - WebRTC calling fixes: Added `UserBlocks::areBlocked()` alias to `UserBlocks::isBlockedEitherWay()`, corrected Carbon date formatting to `toIso8601String()` for call status broadcasts, and auto-cancelled stale ringing calls.

- **AI checks forum photos; only clearly fine ones publish on their own** (not run - `php -l` only, no `vendor/` here; no migration; **set `IMAGE_MODERATION_API_KEY` on the server**, see Setup): `ImageModerationService` sends each photo of a post or comment to a vision model (`IMAGE_MODERATION_API_URL`, default CometAPI's chat endpoint; `IMAGE_MODERATION_MODEL`, default `gemini-3-flash`). Only a clear `approved` publishes. Anything else goes to the admin queue as pending: a photo the model doubts (nudity, violence, anti-state or political-harm content, hate, scams), a format it cannot read (GIF), a file over 8 MB, or a failed call / missing key. Videos and documents still always go to the queue. Text verdicts are unchanged. The photos are checked in parallel inside the request (25 s timeout each), `ContentModerationService::reviewTopicAttachments()` / `applyToComment()`.

- **Public chat sends `profile_theme`** (`php -l` only; no migration): `GET /chat/public/messages`, the message `POST /chat/public/messages` returns / broadcasts, and `GET /chat/public/participants` now carry `ProfileThemeService::forAuthor()` on each member (`sender.profile_theme`, `profile_theme` per participant), like private chat - so the web and app draw their name style, name icon and avatar frame there. Guests stay without one.

- **Teachers have admin rights; the home feed shows members' posts only** (`php -l` only - no `vendor/` here, PHPUnit not run; no migration): `AuthAccount::STAFF_ROLES = ['admin', 'teacher']`, `isStaff()` / `isStaffRole()`, scopes `staff()` / `notStaff()`; `hasRole('admin')`, `scopeRole('admin')` and the `role:admin` middleware accept both, and every `role === 'admin'` / `where('role', 'admin')` check (admin alerts, rankings that leave admins out, gifting, moderation exemption, story / shop chat access, admin-panel protections, subforum role restrictions, Filament) now uses them. **New check for admin rights: use `isStaff()`, never `role === 'admin'`.** `Topic::scopeFromMembers()` drops posts by the youth union account (`forum.school_account`) and by admins / teachers; applied to `GET /topics/feed` (personalized, `mode=latest`, the guest fallback) and to `/home`'s `latestPosts` (all three sorts). Not applied to `mode=following`, `mode=youth-news`, `GET /topics`, forums or profiles. Feed cache key bumped to `feed_scores_v4_*`.

- **Cover pictures for posts without a photo** (not run end to end - no `vendor/` on this machine; the renderer and the ffmpeg frame command were tried on their own; **needs `php artisan migrate`** for `cyo_topics.cover_image`): `TopicCoverService` makes a 1200x750 JPEG per post with no `cdn_image_id` - the **first video frame** (ffmpeg, `FFMPEG_BINARY`, cropped to 16:10) when the post has a video, otherwise the **title + start of the markdown body drawn as a white page** over the brand green (GD + FreeType, Inter fonts in `resources/fonts/Inter`, markdown headings / lists / quotes styled, emoji dropped). Stored in `storage/app/public/covers/`, path in `cover_image`, returned as `cover_url` in `formatTopicForList` (home feed, featured, latest...); it is **not** added to the post's images, so the post page and its "Hình ảnh" label are unchanged. New and edited posts get one through the queue (`Topic` `saved` hook -> `GenerateTopicCover`; needs a running queue worker); the cover file is deleted with the post. **Existing posts: `php artisan topics:generate-covers`** (`--id=`, `--force` to redraw, `--limit=`). A video whose frame can't be read falls back to the text picture.

> Only the newest entries are kept here, so this file stays short enough to read in full. When adding one, drop the oldest; `git log -p -- INFO.md` has everything that was removed.

- **Bug reports & feedback: include `ota_version` for debugging** (**run `php artisan migrate`**): added `ota_version` column to `cyo_feedback` table (`2026_10_30_000001_add_ota_version_to_cyo_feedback_table.php`), `Feedback` model, `FeedbackController::store()`, and admin notification email template (`FeedbackReceivedMail` and `emails.feedback_received`).

- **`GET /v1.0/admin/overview`: a count per sidebar section** (not run - no PHP on this machine; no migration): added `pending_moderation`, `pending_violations`, `new_feedback`, `pending_student_verifications`, `conversations`, `messages_today`, `broadcasts`, `shop_products`, `shop_categories`, `audit_logs_today` next to the existing keys (`AdminPanelController::overview`), so the web `/admin` dashboard can show every sidebar item with its own figure. Existing keys are unchanged.

- **NewYear2027 Preset, Lunar New Year cycle automatic selection & SetAppIcon command update**:
  - `AppConfigController`: Added `NewYear2027` to allowed validation in `POST /v1.0/admin/app-icon`. Automated calendar selects `NewYear2027` for late 2026 / 2027 Tết season (Đinh Mùi).
  - `SetAppIconCommand` (`php artisan app:icon`): Added `NewYear2027` to `VALID_ICONS`.
- **OTA Force Upgrade bounds (`min_version`, `max_version`), All branches option & Lunar New Year season extension**:
  - `AppConfigController`:
    - Extended New Year seasonal icon (`NewYear2026`) to run from Tết Dương Lịch (Dec 28) all the way through the end of Tết Âm Lịch (Lunar New Year / Rằm tháng Giêng, through March 5 for 2026 Tết Bính Ngọ). Added `NewYear` preset alias.
    - Added `min_version` (oldest affected version/build) and `max_version` (highest version/build forced to upgrade) to `ota_update` config and validation in `GET /v1.0/app/config` and `POST /v1.0/admin/app-ota`.
    - Added `all` branch option for target channel (`channel: all`), mandating all branches to force upgrade to the latest build in each respective branch.
  - `SetForceOtaCommand` (`php artisan app:ota`): Added `--min-version=`, `--max-version=`, `--max-build=`, and allowed `--channel=all`.
  - `SetAppIconCommand` (`php artisan app:icon`): Updated seasonal calendar check and accepted `NewYear` alias.
- **Remote Forced OTA Auto-Update & Management (`GET /v1.0/app/config`, `POST /v1.0/admin/app-ota`, `php artisan app:ota`)**:
  - `AppConfigController`: Added `ota_update` payload to `GET /v1.0/app/config` returning `{ force, min_build, channel, message, updated_at }`. Added `POST /v1.0/admin/app-ota` for administrators/staff to mandate OTA auto-updates across mobile clients regardless of client in-app settings.
  - **Artisan Console Command (`php artisan app:ota`)**: Command in `App\Console\Commands\SetForceOtaCommand.php` with `{action?}` (`status`, `force`, `reset`, `unforce`), `--force`, `--min-build=`, `--channel=`, `--message=`, `--status`.
  - **Audit Logging**: All changes are logged into `cyo_audit_logs` under `ADMIN_FORCE_OTA_UPDATE`.

- **Dynamic App Configuration & Seasonal Launcher Icon Directives (`GET /v1.0/app/config`, `POST /v1.0/admin/app-icon`)**:
  - `AppConfigController`: Added dynamic app configuration endpoint serving seasonal icon directives based on Vietnam server calendar (`Asia/Ho_Chi_Minh`):
    - Week of 20/11 (Nov 14 to Nov 21): `TeachersDay` (Kỷ niệm Ngày Nhà giáo Việt Nam 20/11)
    - Week before Christmas (Dec 18 to Dec 27): `Christmas` (Mừng mùa lễ Giáng Sinh)
    - New Year Season (Dec 28 to Jan 5): `NewYear2026` (Chào đón Năm mới 2026)
    - Payload fields: `target_icon`, `force`, `seasonal_icon`, `seasonal_reason`, `admin_override`, `override_reason`, `reason`, `updated_at`, `server_time`.
  - Admin override endpoint (`POST /v1.0/admin/app-icon` with `icon`, `force: boolean`, and optional `reason`) stored in `storage/app/app_config.json`. Allows administrators to either force icon adoption globally or recommend changes for users on the default theme icon.
  - **Audit Log Diary**: Server-side icon changes via admin API and artisan CLI are recorded in `cyo_audit_logs` under action `ADMIN_CHANGE_APP_ICON`.
  - **Search suggestions endpoint with query filtering (`GET /v1.0/search/suggestions?q=...`)**:
  - `app/Http/Controllers/SearchController.php`: Updated `suggestions()` method to accept an optional `q` query string parameter. When `q` is provided, filters trending hashtags (`like %cleanQ%`) and fallback curated topics, returning matching suggestions. When `q` is omitted, returns top trending hashtags.

- **Artisan Console Command (`php artisan app:icon`)**: Added command `app:icon {icon?} {--force} {--reason=} {--status}` in `App\Console\Commands\SetAppIconCommand.php` with full built-in help, status table, audit logging, validation against all native presets, and `reset` action to return to automated calendar mode.

- **AI moderation on post edits** (not run - no PHP on this machine; no migration): `TopicsController::update()` now runs the same AI content-moderation check that `store()` does. On rejection, the edit is blocked (422) and the existing post is left untouched; on `needs_review` the edited content is saved, the post becomes hidden and a new `ModerationQueue` entry is created (any previous pending/rejected entry for the topic is cleared first so admins don't see stale versions); on `approved` the post is visible immediately. The response now always includes `moderation {status, message}`. Admins and teachers (`ContentModerationService::isExempt`) bypass the check. **Frontends updated**: Next.js `CreatePostModal.js` already handled moderation on both create and edit paths. Mobile `PostEditScreen/index.js` (branch `dhphuc`) now shows a Toast and skips the optimistic feed update when `moderation.status === "pending"`; `editPost.pendingModeration/pendingModerationDesc` keys added to vi/en/ru.

- **Passkeys: the certificate a Play Store install is really signed with** (not run; no migration): passkeys worked on the dev build but not on the app installed from Google Play. The installed Play build's signing certificate (read with App Manager on a phone) is SHA-256 `AB:F8:D1:0A:36:F6:EC:C4:58:12:57:B6:94:2A:A2:37:9E:D9:1C:AB:0E:92:5F:14:12:3E:8E:50:7D:17:51:EF`, which neither side listed - the fingerprint added earlier as "the Play key" (`D5:C3...32:BE`) is a different certificate. Added as a third default of `WEBAUTHN_ANDROID_ORIGINS` (`android:apk-key-hash:q_jRCjb27MRYEle2lCqiN57ZHKsOkl8UEj6OUH0XUe8`) and to the web repo's `public/.well-known/assetlinks.json`. **Both are needed**: Android checks assetlinks.json before showing the passkey sheet, the API checks the origin after. **If the server's `.env` sets `WEBAUTHN_ANDROID_ORIGINS`, add the hash there too**, then `php artisan config:cache`. Google caches assetlinks.json, so a phone can take a while to pick the change up.

- **Fix: Yoyo AI's school lookup answered "couldn't check" although the posts exist** (not run - no PHP on this machine; no migration): the reply was the lookup's *failed* branch. The likely cause (not confirmed - check `storage/logs` for "School post lookup") is a config cache built before `forum.school_account` existed: the key read as null, so no account was found. `SchoolKnowledgeService` now falls back to `DoanTruongCBH` when the config has no value, and logs which of the two failures happened (account missing / exception with file and line). **Run `php artisan config:cache` (or `config:clear`) and `php artisan queue:restart` after deploying** - the AI replies run in the queue workers, which keep the old code and config until restarted. The search itself was also reworked, since one subject is usually spread over several posts:
  - a **date is one search term** and matches however it is written (`26/3`, `26/03`, `26-3-2026`, `26 tháng 3`) - before, "26/3" was split into "26" and "3" and never matched a post saying "26/03";
  - words are **weighted by how rare they are** among the matching posts, so "26/3" outweighs "hoạt động"; the rule that two words had to match is gone (it dropped a post titled only "RECAP 26/03");
  - up to **6 posts** (was 4) of 500 characters, each cut around its most telling word; posts with no `description` are read from `content_html`;
  - **the lookup is the model's own call** (prompt only): it answers normally by default and looks up only when it judges the question to be about something the youth union posts (an event, contest, club, announcement, result) - not for every question that mentions the school. After a lookup it uses only the posts that are really relevant, and when none is, it answers from what it knows instead of refusing;
  - the second-pass prompt tells the model to combine what several posts say and to link up to three of them; the first-pass prompt asks for dates as day/month and for names of events instead of generic words.
- **"Pro Plus" tier (2250 points): your own image as avatar frame and profile frame; Yoyo AI reads the school account's posts** (not run - no PHP on this machine; **needs `php artisan migrate`** for `cyo_user_profiles.custom_avatar_frame` / `custom_profile_frame`):
  - **Tier** `pro_plus` ("Thành viên Pro Plus", `AuthAccount::tiers()`), above `pro` (2000, unchanged). Accounts at 2250+ points now get `member_tier = pro_plus`: every client needs an entry for it (web and mobile updated).
  - **Custom frames.** `avatar_frame` and `profile_frame` gained the option `custom` (`ProfileThemeService::CUSTOM_FRAME_TIER`). The image is not in the `profile_theme` JSON: `POST /users/{username}/custom-frame` (multipart `kind` = `avatar` | `profile`, `image`) stores it, `DELETE /users/{username}/custom-frame/{kind}` removes it (`CustomFrameController`, `CustomFrameService`). Themes sent to clients carry the derived `avatar_frame_url` (also in `forAuthor()`) and `profile_frame_url`; with no image, or below the tier, the option reads `none` and the address is null. Saving `custom` with no uploaded image is a 422. `theme_editor.custom_frames` = `{required_points, unlocked, avatar_url, profile_url, rules}`.
  - **Rules** (`CustomFrameService`, sent to clients as `rules`): PNG or WebP, square (within 10%), 256-2048 px, 5 MB, re-encoded to a static PNG (avatar 320 px, profile 480 px; files in `storage/app/public/frames/`). Avatar frame: drawn centred at 1.25x the avatar, and the centre circle (64% of the width) must be transparent - at most 10% of it painted - so it can never cover the face. Profile frame: a nine-slice border - only the outer 25% of each side is drawn (corners keep their shape, edges stretch), the middle never. Both need something visible in the drawn part. Upload answers 403 below the tier (`required_points`), 422 with the broken rule.
  - **No approval queue** (like avatars and covers). Admins take a frame down with `DELETE /admin/users/{id}/custom-frames/{kind}` (audit log `ADMIN_REMOVE_CUSTOM_FRAME`).
  - **Yoyo AI school lookup.** For a question about the school that it cannot answer from the chat, the assistant answers only `[SCHOOL: từ khóa]` (`AiChatService::SCHOOL_LOOKUP_PROMPT`); `GenerateAiChatReply::runAsk` then asks `SchoolKnowledgeService` and calls `askAi` a second time with the result (`$followUp`, the shop assistant's `[PLACE]` pattern; one lookup per reply, the marker is ignored on the second pass). The service searches the public, approved posts of one account (`forum.school_account`, `FORUM_SCHOOL_ACCOUNT`, default `DoanTruongCBH`) by whole words - up to 6 search words, title matches weigh 3x, a word typed with accents must match with them - and returns at most 4 posts as title, date, link and a 600-character excerpt. Read only: the model supplies words, never a query. Applies to `/ai`, the private Yoyo chat and the public room; not the shop assistant.
  - Unit tests added (`CustomFrameServiceTest`, `SchoolKnowledgeServiceTest`) and `ProfileThemeServiceTest` brought up to date with the `pro` / username options it had fallen behind on.

- **Games: the first 17 Famobi embeds point at Famobi's current links** (not run - no PHP on this machine; **run `php artisan db:seed --class=GameSeeder --force`**): their stored `iframe_url`s were old (an expired `fg_beat`), so every game first bounced through play.famobi.com, and three were on an older build - Cannon Balls 3D (v310 -> v320), Basketball Superstars (v050 -> v090), Cooking Rage (v020 -> v050). All 17 were re-resolved through `play.famobi.com/html5game/<uuid>/A1000-10B` and now share that affiliate. The links go stale again when Famobi raises `fg_beat` or ships a new build (the game still loads, one redirect later) - re-resolve them the same way now and then.

- **Games: 50 more Famobi games; the 10 open-source ones are hidden** (not run - no PHP on this machine; no migration, **run `php artisan db:seed --class=GameSeeder --force` on the server**): the open-source games added the entry below were not good enough, so `GameSeeder` no longer lists them and sets `is_active = false` on their rows (hidden, not deleted - their play sessions stay). In their place: 50 games from Famobi's catalogue (html5games.com) - Moto X3M, Slope, 2048, Sudoku, Cut The Rope 1 + 2, Penalty Shooters 2, chess, checkers, bowling, darts... - 68 games listed in all. Each was resolved through `https://play.famobi.com/html5game/<game uuid>/A1000-10B` (the page's `redirectUrl` is the embed link, `gameTeaser` the image) and checked: embed page 200, image 200, and the affiliate does not send the player out of the frame. They all carry Famobi's ads, like the first 17.

- **Games: 10 open-source games added, two Famobi embeds fixed** (not run - no PHP on this machine; no migration, **run `php artisan db:seed --class=GameSeeder --force` on the server**): an audit of the live list (2026-10) found that all 17 Famobi games show ads (a video ad before the game and then at most every 90 s, some also rewarded ads), and that Om Nom Run and Tower Crash 3D - embedded with Famobi's affiliate `A1000-1` - show a "Play" page that opens a new tab and after 15 s navigates the whole page to play.famobi.com (in the app's WebView: out to the browser). Those two now use the `A1000-10B` embed like the rest, which stays inside the frame. Every Famobi game still has a "More Games" button and privacy links that open html5games.com / famobi.com in a new tab. `GameSeeder` adds 10 games hosted by their authors with no ad SDK and no new-tab links (Clumsy Bird, T-Rex, Tower Building, BreakLock, Tetris, Chess, Blockly Maze; HexGL, Underrun, Radius Raid for computers). When adding a game, fetch its page and scripts and check for ad SDKs, `target=_blank` / `window.open` / `top.location`, and `X-Frame-Options` / `frame-ancestors`.

- **`GET /topics/feed?mode=youth-news`** (not run; no migration): the feed's fourth mode beside the personalized one, `latest` and `following` - posts of the youth union news subforum (`Topic::inNewsSubforum()`), newest first, 10 per page, in the feed's post shape (`formatTopicForList`), answered with `mode: "youth-news"`. Unlike the other modes it works for guests. The mobile home feed's "Tin tức Đoàn" tab uses it; `GET /youth-news` (the web page, older shape) is unchanged.

- **Fix: unread chat count with read receipts off** (not run; no migration): `ChatController::getMessages` and `markAsRead` only moved the reader's `last_read_at` when `chat_read_receipts` was on, and `Conversation::unreadMessagesCount()` counts from that timestamp - so for someone who turned read receipts off, a conversation's unread count never returned to 0. `last_read_at` is now always updated; what others can see stays behind the setting (the messages' `read_at`, the `MessageRead` broadcast, and `seenBy()`, which already leaves out members with receipts off).

- **Quiz: site-wide points per finished set are 2 / 4 / 6 for 10 questions, scaled by the set's length** (not run - no PHP on this machine; no migration): `QuizController::globalPointsFor()` - easy 2, medium 4, hard 6 for a 10-question set, proportionally for other lengths (`round(rate x questions / 10)`, at least 1; 5 easy questions = 1, 20 hard = 12). It was a flat 1 / 2 / 3 whatever the length. Unchanged: paid once per set per user, never to the set's creator, not tied to the score; the quiz ranking still counts correct answers x 1 / 2 / 3 (`DIFFICULTY_POINTS`).

- **Game XP: 1 per minute; every 5 XP adds 2 points** (not run - no PHP on this machine; no migration): `GameController::syncSession` gave 1 XP per 10 minutes and added that same number to the site-wide points. Now a session earns **1 XP per full minute** (still computed server-side from `started_at`), and the site-wide points grow by **2 for every 5 XP** of the player's total game XP - counted across all their sessions (`SECONDS_PER_XP`, `XP_PER_POINTS_STEP`, `POINTS_PER_STEP`), so five 1-minute plays count like one 5-minute play. The games leaderboard still ranks by XP. Quiz points are unchanged.
