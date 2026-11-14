# game-proxy

A small Go server that streams a third-party browser game **through our own
origin**, so that

1. the account's **cookies and localStorage** are saved in Laravel and restored
   on another device ("sync the game's save between machines"), and
2. **ad hosts are blocked** (ad `<script>`/`<iframe>`/`<img>` removed from the
   page, dynamic ones stopped by a small script, pop-ups off).

It keeps **no data itself**: Laravel signs the play link and stores the saves.
Standard library only, no dependencies.

```
app / website --POST /v1.0/games/{slug}/proxy-link--> Laravel (signs a token)
iframe src = https://<proxy domain>/play?t=<token>
   proxy: verify token -> load the save from Laravel -> set cookies ->
          redirect to the game's page -> stream the game, rewriting HTML
          (inject the sync + ad-block script) and keeping the cookie jar
   page script + proxy --PUT /v1.0/internal/game-proxy/saves/{user}/{slug}--> Laravel
```

## Run it

Laravel does it for you (config in `config/gameproxy.php`, env `GAME_PROXY_*`):

- `php artisan schedule:run` (the cron you already have) runs
  `game-proxy:ensure` every minute: starts the server if `/healthz` does not
  answer, detached, with a lock file. It also starts it again after a crash or
  a reboot. Nothing else to set up.
- Or run it under Supervisor / systemd: `deploy/supervisor-game-proxy.conf`
  (`php artisan game-proxy:start`) and set `GAME_PROXY_AUTOSTART=false`.

Build the binary once per server CPU/OS (CI or on the host):

```bash
cd game-proxy
go test ./...
CGO_ENABLED=0 go build -ldflags="-s -w" -o bin/game-proxy .
# another CPU: GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build ... 
```

`bin/` is git-ignored: build it in CI or on the server (path: `GAME_PROXY_BINARY`).

## Setup checklist

1. `php artisan migrate` (adds `cyo_game_saves` and `cyo_games.use_proxy`).
2. `.env`: `GAME_PROXY_ENABLED=true`, a long random `GAME_PROXY_SECRET`,
   `GAME_PROXY_PUBLIC_URL=https://<proxy domain>`.
3. **A separate domain** for `GAME_PROXY_PUBLIC_URL` - **not** a subdomain of
   chuyenbienhoa.com. The login cookie is set for `.chuyenbienhoa.com`, so any
   subdomain would send it to the game's scripts. Point it at this server, add
   TLS, and use `deploy/nginx-game-proxy.conf` for the site (aaPanel).
4. Per-game settings live in the database, set in `GameSeeder` like the rest of
   a game's data (`php artisan db:seed --class=GameSeeder --force`): `use_proxy`
   (Famobi games default to on) and `proxy_options`
   `{"block_hosts": [...], "allow_hosts": [...]}` - extra ad hosts for that game,
   and hosts never blocked for it. Laravel puts them in the signed play link, so
   the proxy needs no database. Nothing is proxied until `GAME_PROXY_ENABLED=true`.
5. Clients: `GET /v1.0/games/{slug}` returns `use_proxy`; when true, call
   `POST /v1.0/games/{slug}/proxy-link` and put `url` in the iframe.

## Behaviour worth knowing

- **Token**: `base64url(payload).base64url(HMAC-SHA256)`, payload
  `{u: user id, g: slug, o: game URL, e: expiry}` (`app/Services/GameProxyToken.php`
  <-> `token.go`). The session cookie `cbh_gp` holds the token, so a restart of
  the server loses no session.
- **Saves**: cookies are read from what the browser sends and from the game's
  `Set-Cookie`; localStorage is pushed by the injected script every 15 s, on
  page hide and on tab switch. Last write wins; the page restores the account's
  copy only when it is newer than what the device has (`__gp_v` in
  localStorage). Max `GAME_PROXY_MAX_SAVE_BYTES` (512 KB) per account and game.
  **IndexedDB is not synced** (a game that keeps its save there will not follow
  the account).
- **Rewriting**: HTML/JS/CSS/JSON up to 8 MB have the game's own host replaced by
  ours (so its requests stay on the proxy); everything else is streamed as is.
  Hosts the game uses besides its own load directly from the browser.
- **Ads**: built-in host list in `blocklist.go` + `blocklist.txt` (no rebuild
  needed, restart the server). A game that **waits for an ad to finish** can
  hang when its ad SDK is blocked: the blocked script gets an `error` event, the
  usual "no ad available" path. If a specific game still hangs, add a stub for
  its SDK or take its host off the list.
- **Not covered**: web workers/service workers of the game, games that refuse to
  be proxied, ad networks that detect the proxy. Proxying a third-party game may
  go against its provider's terms (the ads pay for those games) - check before
  switching one on.
