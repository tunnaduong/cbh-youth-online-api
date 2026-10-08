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
- **Push**: Expo push tokens (mobile) and Web Push/VAPID (web), via `PushNotificationService` / `NotificationService`.

## Route access tiers (`routes/api.php`)

1. **Public** — login/register/OAuth/password reset, avatars/covers, file serving, gift-shop catalog, SePay webhook, VAPID key, feedback (throttled), web-session redeem.
2. **`optional.auth`** — works for guests, adds viewer-specific data when a token is sent (forum/topics, profiles, search, stories, study materials, games, public chat, group invite preview). Content from users blocked in either direction is filtered out.
3. **`auth:sanctum` + `not_banned` + `device_session`** — everything that writes or is personal (`device_session` keeps the token's device details current).
4. **`role:admin`** (`CheckRole` middleware) — moderation, report admin, broadcasts, deposit/withdrawal approval, student verifications, and `/v1.0/admin/*` (`Admin\AdminPanelController`), which the web `/admin` uses.

Middleware aliases live in `app/Http/Kernel.php` (`optional.auth`, `not_banned`, `role`, `device_session`).

## Features (domain → main code)

- **Auth & accounts** — `AuthController`: username/password login, register with email verification codes, Google/Facebook (Socialite + PKCE code exchange `POST /oauth/exchange`), Apple (`AppleIdTokenVerifier`), logout (revokes the current token), ban checks (`bannedResponse`), **web-session handoff** (mobile → web: `POST /web-session/handoff` gives a 60-second code, `POST /web-session/redeem` turns it into a separate token named `web-handoff:<app token id>`), **two-factor login** (email code or authenticator app, recovery codes, remembered devices; `TwoFactorController`, `TwoFactorService`, `TotpService`), **logged-in devices** (`DeviceSessionController`) and a new-device login email (`DeviceSessionService`). Password reset: `ForgotPasswordController`, `PasswordResetController`.
- **Users & profiles** — `UserController`, `ProfileController`, `FollowController`, `UserBlockController` (2-way block hides everything; blocked profile returns 404), profile photo gallery, liked-posts list (`GET /users/{username}/likes`), online status (`OnlineUserController`, `ActivityController`), account deletion.
- **Profile customization** — `ProfileThemeService`: avatar frames, name effects, profile effects/frames unlocked by points milestones, and from Pro Plus (2250) an uploaded image as avatar / profile frame (`CustomFrameService`, `CustomFrameController`); returned on authors of posts, comments, chat, rankings and notifications. Spec: PROFILE_THEME_SPEC.md.
- **Forum** — `ForumController`, `TopicsController`: main categories → categories → subforums; topics (posts) with images/video; nested comments (3 levels); votes, views, saved posts (`SavedPostsController`), hidden topics, archive/unarchive, hashtags (`HashtagService`), mentions (`MentionService`), home feed.
- **Moderation** — `ContentModerationService` + `ModerationQueue`, `Admin\ModerationController`; admins are emailed when content enters the queue. User reports: `UserReportController`. School monitoring reports / student violations: `MonitorReport`, `StudentViolation`.
- **Chat** — `ChatController`: 1-1 and group conversations, invite links, reactions, edit/recall/delete, read receipts, attachments (up to ~100MB), chat backgrounds, share-a-post messages with preview, public chat room, **AI chat** (`AiChatService`, `GenerateAiChatReply` job, key `CYO_AI_API`; it can look up the school account's posts, `SchoolKnowledgeService`). Admin access to messages is logged (`AdminMessageAccessLog`).
- **Stories** — `StoryController`: 24h stories with overlays (text, stickers, mentions, links), music, reactions, viewers; `stories:cleanup-expired` runs hourly.
- **Points & wallet** — `PointsService`, `PointsController` (gift points `POST /points/gift`), `DailyCheckinController`, `UserPointDeductionController`, `WalletController`; deposits by **SePay** bank transfer (`PendingDeposit`, `SEPayWebhookController` / `SEPayWebhookService`), withdrawals (`WithdrawalRequest`, admin approve/reject). See POINTS_SYSTEM_README.md.
- **Gift shop** — `ShopController` + `ShopProduct/ShopProductVariant/ShopCategory/ShopOrder/ShopOrderItem`: public catalog, orders (points / QR / COD, optional map pin → `maps_url`), cancelling until an order ships (points refunded, transfers flagged for refund), shop support chat with an AI assistant that can show product photos, draw up an order slip the customer confirms (`POST /shop/support/messages/{id}/order`) and resend the QR to pay; also managed in Filament (`app/Filament/Resources`).
- **Student verification** — `StudentVerificationController` (student KYC; verified students get a shop discount).
- **Study materials** — `StudyMaterialController`, categories, ratings, purchases with points, preview generation (`GenerateStudyMaterialPreview`, pdfparser/phpword).
- **Quiz & games** — `QuizController` (AI-generated questions via Gemini keys `GEMINI_1..5`, `QuizGenerationService`; online only - no question bank, an AI failure returns 503), `CustomQuizController` (quizzes parsed from user documents), `GameController` (play sessions, leaderboard).
- **Notifications** — `NotificationController`, `NotificationSettingsController`, Expo token register/unregister, web push subscribe; weekly newsletter (`newsletter:send-weekly`, Monday 08:00) with unsubscribe.
- **Misc** — `SearchController`, `UniversityController` (proxies Cốc Cốc học tập), `YouthNewsController`, `HelpCenterController`, `FeedbackController` (in-app bug reports/suggestions, guests allowed), `RecordingController`, `FileUploadController` (`/upload`, `/user-content/{id}`; image/video compression jobs), `FacebookWebhookController`.
- **Admin** — Filament panel at `/admin` on this host (shop resources), plus the JSON admin API for the Next.js `/admin` (`Admin\*`, `AdminController`, `AdminBroadcast` + `SendAdminBroadcast` job).
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
deploy/                nginx upload-size snippet, supervisor queue-worker config
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
# fill DB_*, MAIL_*, REVERB_*, GOOGLE_*/FACEBOOK_*/APPLE_CLIENT_ID, VAPID_*, CYO_AI_API, GEMINI_1..5, SePay, RECAPTCHA_*; optional FORUM_SCHOOL_ACCOUNT (default DoanTruongCBH)
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

> Only the newest entries are kept here, so this file stays short enough to read in full. When adding one, drop the oldest; `git log -p -- INFO.md` has everything that was removed.

- **Fix: Yoyo AI's school lookup answered "couldn't check" although the posts exist** (not run - no PHP on this machine; no migration): the reply was the lookup's *failed* branch. The likely cause (not confirmed - check `storage/logs` for "School post lookup") is a config cache built before `forum.school_account` existed: the key read as null, so no account was found. `SchoolKnowledgeService` now falls back to `DoanTruongCBH` when the config has no value, and logs which of the two failures happened (account missing / exception with file and line). **Run `php artisan config:cache` (or `config:clear`) and `php artisan queue:restart` after deploying** - the AI replies run in the queue workers, which keep the old code and config until restarted. The search itself was also reworked, since one subject is usually spread over several posts:
  - a **date is one search term** and matches however it is written (`26/3`, `26/03`, `26-3-2026`, `26 tháng 3`) - before, "26/3" was split into "26" and "3" and never matched a post saying "26/03";
  - words are **weighted by how rare they are** among the matching posts, so "26/3" outweighs "hoạt động"; the rule that two words had to match is gone (it dropped a post titled only "RECAP 26/03");
  - up to **6 posts** (was 4) of 500 characters, each cut around its most telling word; posts with no `description` are read from `content_html`;
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

- **Stories are moderated; admins are exempt from moderation** (not run - no PHP on this machine; **needs `php artisan migrate`** for `cyo_stories.moderation_status`):
  - **Stories** go through `ContentModerationService` like posts and comments (`moderateStory()` on the caption + text stickers, `applyToStory()`). The model cannot see the photo / video, so - as for a post with attachments - a story with media is always held for a human: `moderation_status = pending`, a `cyo_moderation_queue` row with `content_type = story`, admins alerted (`moderation_pending`), the author told (`content_pending_review` with `data.content_type = story`, `story_id`; no email for stories). Text the model rejects: the story is deleted and `POST /stories` answers **422** with `moderation.status = rejected`. Otherwise `POST /stories` answers 201 with `moderation {status: approved|pending, message}`.
  - **Who sees a held story:** only its author (`Story::scopeVisibleModeration`, used by `GET /stories`; each story carries `moderation_status`) and admins (`GET /stories/{id}`). People tagged on it are notified when it becomes visible.
  - **Admin queue** (`Admin\ModerationController`, web `/admin/moderation`): `?type=story` filters; story rows carry `story` `{id, text, media_type, media_url, video_first_frame_url, background_color, expires_at}` (null once the story is gone). Approve publishes it and **restarts its lifetime** (the 24 hours count from the approval); reject keeps it hidden and tells the author (`content_rejected`).
  - **Admins are exempt** (`ContentModerationService::isExempt`): an admin's posts, comments and stories skip the AI check and the queue, and so never produce the pending / approved / rejected emails or notifications.
  - Until the migration has run, stories are published as before (`Story::hasModerationStatus()`).

- **Moderation actions, comment reports, report targets, violation reports** (not run - no PHP on this machine; **needs `php artisan migrate`**):
  - `POST /v1.0/admin/moderation-actions/warn` and `/remove` (`Admin\ContentModerationController`, `role:admin`), body `{content_type: topic|comment|message|story, content_id, note?, report_id?}` (+ `notify` for remove, default true). Warn sends the author a `content_warning` notification that links to the content (post / `#comment-<id>` / `/chat?conversation=&message=` / story); remove deletes it (message: soft delete + `MessageDeleted` broadcast + admin access log) and sends `content_deleted` (no link). Both are built by `NotificationService::createModerationActionNotification` (`data.content_type`, `note`, `excerpt`, plus the ids; delivered whatever the user's notification settings) and logged in `cyo_audit_logs` (`content_warned` / `content_removed`); `report_id` closes that report as resolved. Push titles: `PushNotificationService` (`content_warning`, `content_deleted` named by content type); the push body is the admin's note when given.
  - Reports: `cyo_user_reports.comment_id` (migration `2026_10_12_000001`), `POST /reports` accepts `comment_id` (author taken from the comment), `GET /reports?type=comment`, and every report in `GET /reports` carries `target` `{content_type, content_id, exists, url?, conversation_id?, message_id?, story_id?, excerpt?}` for the panel's links and actions.
  - Fix: banning from a report (`review` with `ban_user`) now sets `banned_at` / `banned_by` - it only set `banned_until`, and `isCurrentlyBanned()` needs `banned_at`, so those bans never applied.
  - Student / class violation reports: table `cyo_violation_reports` (migration `2026_10_12_000002`, model `ViolationReport`), `POST /v1.0/violation-reports` (logged-in users; `type`, `subject_name`, `violation_type`, `report_date`, `notes`, `absences`, `cleanliness`, `uniform`), admin `GET /v1.0/admin/violation-reports` (`type`, `status`, `search`), `POST /{id}/review` (`status`, `admin_notes`), `DELETE /{id}`. The app's report flow sent nothing before.

- **Quizzes are online only** (not run - no PHP on this machine): `QuizController::start` no longer saves AI-generated questions into the question bank (`QuizQuestion`, `cyo_quiz_questions`) or marks them seen (`cyo_quiz_question_seen`), and no longer falls back to bank questions when the AI call fails - it answers 503 "Không thể tạo câu hỏi lúc này…". The played set is still stored as a `QuizSet` (needed to grade answers and for share links). The model, its tables and `php artisan quiz:clear-cached` are kept, unused, so the existing bank rows can be cleared.
