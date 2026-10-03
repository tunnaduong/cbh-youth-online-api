# INFO — CBH Youth Online — API

> **For AI agents.** Project map for agents working in this repo: how it connects to the sibling repos, features, structure, setup, conventions and recent work. Humans: see `README.md`. Keep this file current - add to **Recent work** and update other sections whenever you change the repo.

- **Default branch: `main`** - work, commit and push there unless the user names another branch.

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

- **Auth & accounts** — `AuthController`: username/password login, register with email verification codes, Google/Facebook (Socialite + PKCE code exchange `POST /oauth/exchange`), Apple (`AppleIdTokenVerifier`), logout (revokes the current token), ban checks (`bannedResponse`), **web-session handoff** (mobile → web, see Recent work), **two-factor login** (email code or authenticator app, recovery codes, remembered devices; `TwoFactorController`, `TwoFactorService`, `TotpService`), **logged-in devices** (`DeviceSessionController`) and a new-device login email (`DeviceSessionService`). Password reset: `ForgotPasswordController`, `PasswordResetController`.
- **Users & profiles** — `UserController`, `ProfileController`, `FollowController`, `UserBlockController` (2-way block hides everything; blocked profile returns 404), profile photo gallery, liked-posts list (`GET /users/{username}/likes`), online status (`OnlineUserController`, `ActivityController`), account deletion.
- **Profile customization** — `ProfileThemeService`: avatar frames, name effects, profile effects/frames unlocked by points milestones; returned on authors of posts, comments, chat, rankings and notifications. Spec: PROFILE_THEME_SPEC.md.
- **Forum** — `ForumController`, `TopicsController`: main categories → categories → subforums; topics (posts) with images/video; nested comments (3 levels); votes, views, saved posts (`SavedPostsController`), hidden topics, archive/unarchive, hashtags (`HashtagService`), mentions (`MentionService`), home feed.
- **Moderation** — `ContentModerationService` + `ModerationQueue`, `Admin\ModerationController`; admins are emailed when content enters the queue. User reports: `UserReportController`. School monitoring reports / student violations: `MonitorReport`, `StudentViolation`.
- **Chat** — `ChatController`: 1-1 and group conversations, invite links, reactions, edit/recall/delete, read receipts, attachments (up to ~100MB), chat backgrounds, share-a-post messages with preview, public chat room, **AI chat** (`AiChatService`, `GenerateAiChatReply` job, key `CYO_AI_API`). Admin access to messages is logged (`AdminMessageAccessLog`).
- **Stories** — `StoryController`: 24h stories with overlays (text, stickers, mentions, links), music, reactions, viewers; `stories:cleanup-expired` runs hourly.
- **Points & wallet** — `PointsService`, `PointsController` (gift points `POST /points/gift`), `DailyCheckinController`, `UserPointDeductionController`, `WalletController`; deposits by **SePay** bank transfer (`PendingDeposit`, `SEPayWebhookController` / `SEPayWebhookService`), withdrawals (`WithdrawalRequest`, admin approve/reject). See POINTS_SYSTEM_README.md.
- **Gift shop** — `ShopController` + `ShopProduct/ShopProductVariant/ShopCategory/ShopOrder/ShopOrderItem`: public catalog, orders (points / QR / COD), cancel rules, shop support chat; also managed in Filament (`app/Filament/Resources`).
- **Student verification** — `StudentVerificationController` (student KYC; verified students get a shop discount).
- **Study materials** — `StudyMaterialController`, categories, ratings, purchases with points, preview generation (`GenerateStudyMaterialPreview`, pdfparser/phpword).
- **Quiz & games** — `QuizController` (AI-generated questions via Gemini keys `GEMINI_1..5`, `QuizGenerationService`), `CustomQuizController` (quizzes parsed from user documents), `GameController` (play sessions, leaderboard).
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
tests/                 PHPUnit (Feature: auth, profile, voting; Unit: ProfileThemeService, story overlay sanitizer, TotpService)
.rr.yaml, rr           RoadRunner binary + config (Laravel Octane)
```

## Setup & run

> `php` is **not installed** in the current dev container (Termux/PRoot): you can edit code but can't run artisan or tests here. Verify on a machine with PHP.

Requirements: PHP ^8.1, Composer, MySQL, Node (only for the legacy Vite/Inertia assets), optionally Redis (predis).

```bash
composer install
npm install                 # also runs patch-package
cp .env.example .env && php artisan key:generate
# fill DB_*, MAIL_*, REVERB_*, GOOGLE_*/FACEBOOK_*/APPLE_CLIENT_ID, VAPID_*, CYO_AI_API, GEMINI_1..5, SePay, RECAPTCHA_*
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

- **Profile name style: new tier, fonts and effects** (not run): member tier `premium` ("Thành viên cao cấp", 1500 points, `AuthAccount::tiers()`); name fonts `flex` (Google Sans Flex) and `grotesk` (Space Grotesk), available from the base tier; name effects `rainbow` and `outline`, unlocked at `premium` (`ProfileThemeService::OPTIONS`). `outline` uses `name_colors[0]` as the text colour and `name_colors[1]` as the border colour, so the stored shape is unchanged. Accounts at 1500+ points now get `member_tier.id = premium`: clients need an entry for it (web and mobile updated; the gift shop was not checked).

- **Admin: reset a user's password / two-factor** (not run): `POST /v1.0/admin/users/{id}/reset-password` sets a random 12-character temporary password, returns it once (`password`) and logs the account out everywhere; refused for your own account and for other admins. `POST /v1.0/admin/users/{id}/reset-two-factor` turns two-factor off (password untouched). `GET /admin/users` rows now include `two_factor_confirmed_at` (set while any method is on).

- **2FA: several methods at once** (not run; needs `php artisan migrate` for `two_factor_totp_confirmed_at` / `two_factor_email_confirmed_at`, which are backfilled from the old single-method columns). The authenticator app and the email code are now independent and can both be on. `two_factor_method` is kept as the default method and `two_factor_confirmed_at` as "any method is on". Changes to the contract described in the entry below:
  - Status (`GET /two-factor`) adds `methods` (every method that is on); `method` is the default one (authenticator first).
  - Setup of a method is allowed while another is on; `POST /two-factor/confirm` takes `method` and returns `recovery_codes: null` when two-factor was already on (the existing codes stay valid). `POST /two-factor/disable` takes `method` to turn one off (the last one turns two-factor off); without it everything is turned off. Cancelling an unconfirmed setup needs no password/code.
  - Login challenge adds `methods` and `email_sent`; a code is only emailed up front when email is the default method. `POST /login/two-factor` takes `method` (optional: without it the code is tried against each method), and `POST /login/two-factor/resend` also does the first send when the user picks email.

- **Two-factor login, logged-in devices, new-device email** (branch `feat/two-factor-auth`, written on a machine without PHP: **not run yet** - run `php artisan migrate` and `php artisan test --filter=TotpServiceTest` before merging):
  - **2FA, off by default.** Methods: emailed 6-digit code (`TwoFactorCode` notification) or authenticator app (`TotpService`, RFC 6238, no package). `TwoFactorService` holds the logic. Columns `two_factor_*` on `cyo_auth_accounts` (secret and recovery-code hashes are encrypted casts on `AuthAccount`; `hasTwoFactorEnabled()`).
  - **Login**: `POST /login` and `POST /login/oauth` return `{ two_factor_required, challenge_token, method, email, expires_in }` instead of a token when 2FA is on. `POST /login/two-factor` (`challenge_token`, `code` = app/email/recovery code, `remember_device`, `device_name`, `device_token`) then returns the normal login payload plus `device_token`. `POST /login/two-factor/resend` re-sends the email code. An expired challenge answers **410** with `challenge_expired` (not 401, which clients treat as a dead session). Challenges, email codes and failure counters live in `Cache`/`RateLimiter` (10 min TTL, 5 tries per challenge, 10 wrong codes per account per 15 min).
  - **Remembered devices**: `cyo_two_factor_trusted_devices` (60 days); clients send the token back as `device_token` on `/login` and `/login/oauth` to skip the challenge. Forgotten on password change/reset and on "log out other devices".
  - **Settings** (`TwoFactorController`, auth): `GET /two-factor`, `POST /two-factor/{totp,email,email/send,confirm,disable,recovery-codes}`, `DELETE /two-factor/trusted-devices`. Setup needs the password unless the account came from a social provider; disabling needs the password or a current code.
  - **Logged-in devices** (`DeviceSessionController`): `GET /sessions`, `DELETE /sessions/{id}`, `DELETE /sessions` (all except the current token). `personal_access_tokens` gained `platform`, `app_version`, `device_name`, `device_model`, kept current by the `device_session` middleware (`TrackDeviceSession`) from the URL-encoded headers `X-Client-Platform` (`web`/`ios`/`android`), `X-Client-Version`, `X-Device-Name`, `X-Device-Model`.
  - **New-device email**: `DeviceSessionService::recordLogin()` runs when a login issues a token; an unseen platform+name+model (table `cyo_known_devices`) sends `NewDeviceLogin`. It counts as a security email, so there is no setting to turn it off. Every account gets one on its first login after deploy.
  - **Legacy session login** (`routes/auth.php`, `SocialAuthController`) refuses accounts with 2FA on, since it has no second step.
  - **Still open**: `loginWithProvider` trusts a client-supplied email when the provider returns none (account takeover for accounts without 2FA); 2FA is not forced for admins; no feature tests for these endpoints.

- **Web-session handoff (mobile → web)**: `POST /v1.0/web-session/handoff` (auth) stores a random 64-char code → user id in `Cache` for 60s. `POST /v1.0/web-session/redeem` (public, `throttle:20,1`) pulls the code once, checks the ban, and returns a **separate** Sanctum token named `web-handoff`, so logging out on the web doesn't log out the app. The mobile app's in-app browser uses it by opening `…/auth/set-token?code=…&return=…` on chuyenbienhoa.com and giftshop.chuyenbienhoa.com.
- Posts: moderation-approved posts no longer flagged as edited.
- Comments: replying to a level-2 comment now creates level 3 (#48).
- Profile customization: themes unlocked by points milestones; name style + avatar frame returned on post/comment/chat/ranking/notification authors (#47).
- Gift shop support chat (#46).
- `GET /users/{username}/likes` (posts making up total likes, sortable).
- Points: milestones follow the real balance; admin point edits write wallet history; gift points `POST /points/gift`.
- Blocking hides all content both ways; blocked profile returns 404.
- Profile photo gallery API; post archive (archive/unarchive/list).
- Chat: share posts in messages with preview; push text "Đã chia sẻ một bài viết".
- Stories: overlays (text, stickers, mentions, links) and music are saved.
- Moderation: admins emailed when a post/comment enters the queue.
