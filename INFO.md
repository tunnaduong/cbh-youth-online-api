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

- **Auth & accounts** — `AuthController`: username/password login, register with email verification codes, Google/Facebook (Socialite + PKCE code exchange `POST /oauth/exchange`), Apple (`AppleIdTokenVerifier`), logout (revokes the current token), ban checks (`bannedResponse`), **web-session handoff** (mobile → web, see Recent work), **two-factor login** (email code or authenticator app, recovery codes, remembered devices; `TwoFactorController`, `TwoFactorService`, `TotpService`), **logged-in devices** (`DeviceSessionController`) and a new-device login email (`DeviceSessionService`). Password reset: `ForgotPasswordController`, `PasswordResetController`.
- **Users & profiles** — `UserController`, `ProfileController`, `FollowController`, `UserBlockController` (2-way block hides everything; blocked profile returns 404), profile photo gallery, liked-posts list (`GET /users/{username}/likes`), online status (`OnlineUserController`, `ActivityController`), account deletion.
- **Profile customization** — `ProfileThemeService`: avatar frames, name effects, profile effects/frames unlocked by points milestones; returned on authors of posts, comments, chat, rankings and notifications. Spec: PROFILE_THEME_SPEC.md.
- **Forum** — `ForumController`, `TopicsController`: main categories → categories → subforums; topics (posts) with images/video; nested comments (3 levels); votes, views, saved posts (`SavedPostsController`), hidden topics, archive/unarchive, hashtags (`HashtagService`), mentions (`MentionService`), home feed.
- **Moderation** — `ContentModerationService` + `ModerationQueue`, `Admin\ModerationController`; admins are emailed when content enters the queue. User reports: `UserReportController`. School monitoring reports / student violations: `MonitorReport`, `StudentViolation`.
- **Chat** — `ChatController`: 1-1 and group conversations, invite links, reactions, edit/recall/delete, read receipts, attachments (up to ~100MB), chat backgrounds, share-a-post messages with preview, public chat room, **AI chat** (`AiChatService`, `GenerateAiChatReply` job, key `CYO_AI_API`). Admin access to messages is logged (`AdminMessageAccessLog`).
- **Stories** — `StoryController`: 24h stories with overlays (text, stickers, mentions, links), music, reactions, viewers; `stories:cleanup-expired` runs hourly.
- **Points & wallet** — `PointsService`, `PointsController` (gift points `POST /points/gift`), `DailyCheckinController`, `UserPointDeductionController`, `WalletController`; deposits by **SePay** bank transfer (`PendingDeposit`, `SEPayWebhookController` / `SEPayWebhookService`), withdrawals (`WithdrawalRequest`, admin approve/reject). See POINTS_SYSTEM_README.md.
- **Gift shop** — `ShopController` + `ShopProduct/ShopProductVariant/ShopCategory/ShopOrder/ShopOrderItem`: public catalog, orders (points / QR / COD), cancel rules, shop support chat with an AI assistant that can show product photos, draw up an order slip the customer confirms (`POST /shop/support/messages/{id}/order`) and resend the QR to pay; also managed in Filament (`app/Filament/Resources`).
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

- **Passkeys belong to `www.chuyenbienhoa.com`** (relying-party id; was the bare domain; not run): the mobile app's native passkeys were refused by Android after the system sheet because `https://chuyenbienhoa.com/.well-known/assetlinks.json` answers with a redirect to www, which Google (and Apple) do not follow; on www the check passes. Default of `WEBAUTHN_RP_ID` changed - **if the server's `.env` sets `WEBAUTHN_RP_ID`, change it there too**. Passkeys created before this change were bound to the bare domain and can no longer be used: their owners log in with the password and add a passkey again.

- **Gift shop AI: saved address book** (syntax-checked in CI only; **needs `php artisan migrate`** for `cyo_shop_addresses`). Once the assistant has a recipient, phone and address from the customer it ends its answer with `[ADDRESS]{"recipient_name","phone","address","place","street","ward","district","province"}[/ADDRESS]` (cut out of the text like the other markers): the full address line plus the same address split into parts by the model. `GenerateAiChatReply` runs it through `ShopAddress::parse()` (same checks as an order slip: name, `0` + 8-10 digit phone, address of 10+ characters) and `ShopAddress::remember()` saves it - one row per distinct name + phone + address (`fingerprint`), `last_used_at` bumped when seen again, at most 8 per customer. A valid order slip saves its delivery details the same way. The shop context then lists the book as "Sổ địa chỉ đã lưu của khách" (most recently used first, with the parts), and the AI offers those back on the next order; the list derived from past orders is only used while the book is empty. Until the migration has run nothing is saved and nothing breaks: `remember()` and `forCustomer()` swallow the missing-table error.

- **Gift shop AI: looser addresses, place lookup, returning customers, chat opens without a product** (syntax-checked in CI only; no migration):
  - **Addresses.** The prompt no longer demands every administrative level: a named place plus its town/province is enough, or a street plus one wider level; the AI asks again only for a really vague address, once, and accepts the customer's word. No place names are hardcoded in the prompt.
  - **Place lookup.** For a named place the model answers with only `[PLACE:name, town]`; `GenerateAiChatReply::runShopSupport` then asks `PlaceLookupService` (Photon, `photon.komoot.io` - free OpenStreetMap search, no key, boxed to Vietnam) and calls `askShopSupport` a second time with the results (`$placeResults`), so the AI can confirm the place, list same-named places/campuses and ask which, or ask for one more detail when nothing is found. One lookup per reply; a failed lookup is reported to the model as "couldn't check", not as "doesn't exist". The prompt also tells the model to use its own web search when the endpoint gives it one (the chat endpoint runs on gemini-web2api) - nothing in this code switches that on.
  - **Returning customers.** The shop context now lists up to 4 distinct delivery details from the customer's last 15 orders (address + phone; chat orders store "name - address"). The AI offers them back ("same as last time?" / "which one?") and may only use one after the customer confirms. `buildOrderDraft` strips a leading "name - " so the name isn't doubled. This reverses the earlier "no phone/address in the context" choice, at the owner's request - those details now go to the AI endpoint.
  - **`POST /shop/support/open`** (`ShopController::openSupport`): returns `{conversation_id, admins_online, ai_enabled}` for the caller's support thread, creating it if needed and posting nothing - for the shop's always-visible chat button. Thread find-or-create moved out of `contactShop` into `supportThreadFor()`.

- **Gift shop AI: order, pay and see product photos in the support chat** (not run; no migration). The shop assistant (`AiChatService::askShopSupport`, `SHOP_SUPPORT_PROMPT`) may end its answer with markers, which are cut out of the text and never shown: `[IMAGE:product(:variant)]` (up to 3), `[ORDER]{json}[/ORDER]` and `[PAY:order]`. The model only names ids; `GenerateAiChatReply::runShopSupport` resolves them against the database and stores the result in the AI message's `metadata`, which the shop widget draws:
  - `shop_images`: `[{product_id, variant_id, name, variant_label, price, image_url}]` - only active products that have a photo.
  - `shop_order_draft`: an order slip (`items` with name/price/photo, `recipient_name`, `phone`, `address`, `shipping_address` = "name - address", `payment_method`, `note`, `discount_percent`, `subtotal`, `shipping_fee`, `total`, `order_id`). `buildOrderDraft()` refuses an incomplete order (unknown/off-sale product, missing variant, quantity over stock, no recipient name, phone not `0` + 8-10 digits, address under 10 characters, no payment method, not enough points) and appends the reason to the reply instead. The prompt tells the AI to collect all of that from the customer first and never to invent it.
  - `shop_payment`: `{order_id, …buildQrPayment}` for the customer's own unpaid QR order.
  - **`POST /shop/support/messages/{messageId}/order`** (`ShopController::confirmChatOrder`, thread owner only) places the order from the stored slip - nothing is ordered until the customer presses confirm. One order per slip (row lock; a second call returns the same order), slips expire after 24h, business errors come back as 422 with a message, and a system message tells staff an order came from the chat. Order creation was pulled out of `storeOrder` into `placeOrder()` (same behaviour) so both paths share it.
  - Context given to the AI now also has the whole active catalogue with ids (`#product`, `[variant]`, stock, "có ảnh"), the student discount, and the last 5 orders with a spelled-out payment state: for an unpaid QR order the bank, account, amount and exact transfer content, so it can answer "have I paid?" from data. It is told never to confirm a payment the data doesn't show.
  - `.github/workflows/lint.yml` runs `php -l` on pull requests (the code here is often written on machines without PHP, and `main` deploys by itself).

- **Appearance in the lists that lacked it; anonymous authors hidden in search** (not run): `GET /search` users now carry `profile_theme` and `verified`; search **posts** no longer name the author of an anonymous post (same masked shape as the feed, plus `anonymous`). Post and comment vote lists (`TopicsController::getVotes`, `getVotesForComment`), followers / following and story groups / viewers gained `profile_theme` and/or `verified`. Also fixed a broken class reference in the login payload's `profile_theme` (it would have failed every login).

- **"Pro" tier at 2000 points** (id `pro`, shown as "Thành viên Pro", `AuthAccount::tiers()`; not run). What it unlocks, all through `ProfileThemeService`:
  - **Icon after the name**: by default clients show the icon of the member's current tier (`member_tier`). From Pro it can be changed: `name_icon` = `tier_<id>` (any tier's icon; themes then carry `name_icon_tier`, editor options carry `tier`) or a preset glyph (`name_icon_emoji`).
  - **Name icon**: `profile_theme.name_icon` (`none` or one of 30 playful presets - fish, cat, frog, rocket, boba... - never a tick, so the verified badge stays unmistakable). The glyph is in `NAME_ICONS`; themes sent to clients carry it as `name_icon_emoji` (derived, never stored) and the editor options carry `icon`.
  - **Styled username**: `profile_theme.username_style` = `default` | `name` (the `@username` is drawn with the name's font and effect).
  - Both are ordinary `OPTIONS` entries (validated, tier-locked and listed in `editorState()` like the others) and are part of `forAuthor()`, so they arrive wherever a name is shown: posts, comments, stories, chat, search, rankings, followers, notifications. The login payload's `user` now also has `profile_theme` (the `forAuthor()` part).
  - **Fancy names**: emoji and decorative Unicode (𝟐𝟖 𝓓𝓪𝔂𝓼 𝓛𝓪𝓽𝓮𝓻, fullwidth, circled...) in `profile_name` need the tier. Below it `PUT` profile answers 422 with `errors.profile_name` when a **changed** name is not plain (`isPlainName()`: letters of ordinary scripts with their accents, digits, space, `. , ' _ -`). A name set earlier is never re-checked. `editorState()` adds `fancy_name {required_points, unlocked}`.

- **Two-factor by approval on a logged-in device** (method `device`; not run; needs `php artisan migrate` for `cyo_auth_accounts.two_factor_device_confirmed_at`): like GitHub Mobile / Facebook. `LoginApprovalService` (all state in `Cache`, 5 min):
  - Enable: `POST /two-factor/device` (password, like the other setups; no code to confirm; returns `recovery_codes` when it is the first method). Disable: `POST /two-factor/disable {method: "device"}`. `methods` in the status and in the login challenge now may contain `device`.
  - Device logging in: `POST /login/two-factor/approval {challenge_token}` → `{number, expires_in}` (a two-digit number to display; at most 5 requests per challenge, each replaces the previous), then poll `POST /login/two-factor/approval/status {challenge_token, remember_device?, device_name?, device_token?}` → `{status: pending|denied|expired}` or, once approved, the normal login payload plus `status: approved` (consumed once).
  - Logged-in devices: told by Expo push + web push (`data.type = login_approval`, `approval_id`) and the realtime event `login.approval` on `App.Models.User.{id}` (`LoginApprovalRequested`, carries only the id). `GET /two-factor/approvals` → `approvals: [{id, numbers:[3], platform, device_name, device_model, ip, created_at, expires_at}]`; `POST /two-factor/approvals/{id} {approve: bool, number}` - approving needs the number shown on the new device; a wrong number denies the request and counts towards the wrong-code limiter.
  - Limits: an account whose only method is `device` and that is logged in nowhere else can only get in with a recovery code; clients older than this feature don't know the method.

- **Logged-in devices show how each login was made** (not run; needs `php artisan migrate` for `personal_access_tokens.login_method` + `login_two_factor`): `GET /sessions` rows add `login_method` (`password`, `google`, `facebook`, `apple`, `passkey`, `register` = signed up on that device, `app` = web session handed over by the mobile app; null for older logins) and `login_two_factor` (the login went through the two-factor step). Written by `DeviceSessionService::recordLoginMethod()` from `AuthController::loginResponseData($user, $request, $method, $twoFactor)`, the register and web-handoff paths; it never fails a login, also before the migration has run.

- **Passkeys from the native mobile app; device-only passkeys; admins have no rank** (not run):
  - **Android app as a passkey origin**: the mobile app now asks for passkeys natively. iOS reports the origin `https://chuyenbienhoa.com` (already allowed); Android reports `android:apk-key-hash:<base64url sha256 of the APK's signing certificate>`, which `WebAuthnService::origins()` now accepts from `services.webauthn.android_origins` (`WEBAUTHN_ANDROID_ORIGINS`, comma-separated; the default is the certificate the GitHub builds are signed with). **A build signed with another key needs its hash added here**, and its fingerprint in the web repo's `public/.well-known/assetlinks.json`. The app now uses `POST /login/passkey` directly; `app_challenge` / `/login/passkey/redeem` stay for older app versions.
  - **Device-only**: registration options ask for `authenticatorAttachment: platform` and both option sets carry `hints: ['client-device']`, so browsers and phones go straight to the device's own authenticator instead of offering a phone over QR code or a security key. It is a request to the client, not something the server can verify (attestation is `none`).
  - **`GET` current points: `rank` is null for admins** (`UserController::getCurrentPoints`). Admins are left out of the ranking, and counting "members with more points" told the top admin they were #1.

- **`session.revoked` broadcast**: `DeviceSessionController::destroy` / `destroyOthers` broadcast `DeviceSessionsRevoked` (`ShouldBroadcastNow`, private `App.Models.User.{id}`, `{ token_ids }`) so the mobile app signs out at once when its login is revoked from the devices list. Best effort (try/catch); a client that misses it signs out on its next 401.
- **Fix: gift shop support thread is found by who opened it** (not run): `ShopController::contactShop()` looked for "any support thread the user is in", and a shop admin is a member of every customer's thread - so an admin's own inquiry was posted into another customer's thread, where the AI switch (`PUT /shop/support/{id}/ai`, checks `created_by`) answered 404 and the AI never replied. It now matches `created_by`. `GET /shop/support/status` also returns `conversation_id` (the caller's own thread, or null) so the shop widget can drop a thread id remembered from another account.

- **Two-factor on social logins is the user's choice** (not run; needs `php artisan migrate` for `cyo_auth_accounts.two_factor_skip_social`, default true): `POST /login/oauth` only answers with a two-factor challenge when the account turned the skip **off** (`AuthAccount::skipsTwoFactorOnSocialLogin()`; treated as on while the column doesn't exist yet). `GET /two-factor` adds `skip_social_login`; `PUT /two-factor/social-login {skip: bool}` sets it (no password asked). When the challenge does run for a social login matched by email, passing it links the provider (`TwoFactorService::challengeFor($user, $deviceToken, $link)` / `challengeLink()`). The legacy session login (`SocialAuthController`) follows the same setting. Passkey login was checked on production: the API answers correctly; the fault was on the web (silent failure when the device has no passkey, web PR 33).

- **Security and correctness fixes from a review of the 2FA / passkey / shop work** (not run):
  - **Social login no longer trusts an email the client sends** (`AuthController::loginWithProvider`, `$emailTrusted`): an email that comes from the request body (`profile.email`, Apple's first-login email) is never used to find an existing account, and is dropped if another account already owns it; `email_verified_at` is only set for an email the provider vouched for. This closes the takeover noted as "still open" below.
  - **Passkeys need user verification** (`WebAuthnService`: `userVerification: 'required'`, UV flag checked at register and login; a sign counter that goes backwards is refused) - a passkey login skips two-factor, so the device must have checked the person. **Passkeys are deleted when the password is changed or reset** (`PasswordResetController`, `ForgotPasswordController`), like remembered devices.
  - The wrong-code limiter (`TwoFactorService::hitFailure`) now also counts wrong **passwords** on two-factor setup and on adding a passkey. `TwoFactorService::challengeFor` returns null when no method is usable. `TrackDeviceSession` can no longer fail a request (try/catch).
  - **Filament panel** (`/admin` on this host): `AuthAccount` implements `FilamentUser`; `canAccessPanel()` allows admins only, and **not admins with two-factor on** (that login form has no second step - they use the web admin).
  - Admin "turn off two-factor" refuses other admins. `ShopController::replaceCart` locks the account row so two devices saving at once can't interleave.
- **Upload compression moved to the clients** (not run): the API no longer compresses uploaded photos or videos - every `ProcessImageCompression::dispatch` / `ProcessVideoCompression::dispatch` in `FileUploadController`, `ChatController`, `StoryController` and `TopicsController` (comment images) is **commented out, not deleted**, and `/upload` stores videos with `video_status = completed`. The web and mobile clients compress before uploading (photos: 1470px wide, JPEG quality 80; videos: 720p, at most 30 fps, H.264 at up to 2.5 Mbps). Still server-side: HEIC conversion, avatar cropping, video thumbnails/preview GIFs. The two job classes and the `videos:compress-existing`, `videos:reencode-hevc` and photo commands remain for manual use. Uploads from clients that can't compress (old app versions, browsers without WebCodecs, Android builds without the compressor) are stored as sent.

- **Videos are compressed to H.264 instead of H.265** (not run): `ProcessVideoCompression` used `libx265`, which Chrome and Firefox can't decode, so post/chat/story videos played sound only on the web and looked like audio players. It now encodes H.264 High / yuv420p (even dimensions forced). Already-compressed videos are still H.265: run `php artisan videos:reencode-hevc` once on the server (`--dry-run` lists them, `--queue` sends them to the queue worker). It needs `ffmpeg`/`ffprobe` and rewrites each file in place.

- **Passkey login, server-hosted name fonts, gradient theme colours, story name styles, gift shop AI support + synced cart** (not run; needs `php artisan migrate` for `cyo_passkeys`, `cyo_conversations.shop_ai_enabled` and `cyo_shop_cart_items`):
  - **Passkey login (separate from two-factor)**: `WebAuthnService` (hand-rolled WebAuthn: "none" attestation, ES256/RS256, no package). A passkey login needs no password and **skips two-factor**. Public: `POST /login/passkey/options` → `{request_id, publicKey}`, `POST /login/passkey` (`request_id`, `credential`) → normal login payload. The mobile app runs the prompt in its in-app browser on the web site: it sends `app_challenge` (sha256 of a secret) and gets a one-time `code`, redeemed by the app with `POST /login/passkey/redeem` (`code`, `verifier`). Manage (auth, `PasskeyController`): `GET /passkeys`, `POST /passkeys/options` (password), `POST /passkeys`, `DELETE /passkeys/{id}`. Config `services.webauthn` (`WEBAUTHN_RP_ID`, `WEBAUTHN_ORIGINS`; defaults chuyenbienhoa.com). The relying-party id must not change once users have registered. Admin password reset also deletes the user's passkeys.
  - **Server-hosted name fonts**: 15 fonts in `public/fonts/name` (static .ttf from Google Fonts, all with Vietnamese glyphs; see `LICENSE.txt`), registry `ProfileThemeService::SERVER_FONTS`, all at the `premium` tier (including `flex` and `grotesk`, moved up from the base tier). `GET /v1.0/name-fonts` lists `{key, label, family, url, required_points}`; `GET /v1.0/name-fonts/{file}.ttf` serves the file through Laravel so it has CORS headers. Editor options for these keys carry `label`. To add a font: drop the .ttf, add it to `SERVER_FONTS` and `OPTIONS['name_font']` - no client release needed.
  - **Gradient theme colours (premium)**: `primary_color_2`, `accent_color_2`, `banner_color_2` (nullable hex; second colour of a gradient). Rejected on save and nulled in `forDisplay()` below 1500 points; `editorState()` adds `color_gradient {required_points, unlocked}`; `forAuthor()` includes the primary/accent ones.
  - **Stories**: `GET /stories` groups and story viewers now include `profile_theme` (name style + avatar frame).
  - **Gift shop support AI**: in `is_shop_support` threads every text message from the customer gets an AI answer (`GenerateAiChatReply` mode `shop`, `AiChatService::askShopSupport` with its own prompt). Context sent to the AI: customer name/username/points, thread name and staff members, the product last asked about (category, price, stock, variants, description), the customer's recent orders (no phone/address) - extended since, see the newest entry. `cyo_conversations.shop_ai_enabled` (default on); `PUT /shop/support/{conversationId}/ai {enabled}` (customer only) toggles it and posts a system message for staff; `contactShop` accepts `variant_id` and returns `ai_enabled`, `GET /shop/support/status` returns it too. Staff messages never trigger the AI; slash commands are ignored in support threads. Needs the `is_ai` account and a running queue worker.
  - **Gift shop cart sync**: `GET /shop/cart`, `PUT /shop/cart {items:[{product_id, variant_id, quantity}]}` (replaces the cart) on `cyo_shop_cart_items`.

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
  - **Still open**: 2FA is not forced for admins; no feature tests for these endpoints.

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
