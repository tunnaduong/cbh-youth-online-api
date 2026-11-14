<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayerState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Serves a game's files through the API instead of the game host.
 *
 * - Ads: the game's bootstrap (the Famobi API script that redirects the frame
 *   to the "Play" wrapper and loads the ad stack) is removed from the page.
 *   A small shim takes its place: it loads the game's own scripts, answers the
 *   game's API calls with "no ad", and keeps the frame from opening tabs.
 * - Cookies: cookies the game server sets are kept per player on the server
 *   (cyo_game_cookie_jars) and replayed on that player's requests, so the same
 *   player has the same cookies on every device.
 * - Only the game's own host and folder are ever fetched; redirects are not
 *   followed, so a game cannot send the proxy to another site.
 *
 * Player identity travels in the URL as a signed, time-limited ticket, because
 * the game runs in an iframe that cannot send a bearer token.
 */
class GameProxyService
{
    public const TICKET_TTL_SECONDS = 6 * 3600;

    private const MAX_CACHED_BODY_BYTES = 5_000_000;

    private const CACHE_TTL_SECONDS = 86400;

    private const MAX_COOKIES_PER_JAR = 50;

    private const MAX_COOKIE_VALUE_BYTES = 4096;

    public const MAX_STORAGE_KEYS = 300;

    public const MAX_STORAGE_VALUE_BYTES = 65536;

    public const MAX_STORAGE_BYTES = 524288;

    // Sandbox without allow-same-origin: the game gets an opaque origin, so it
    // cannot read this site's cookies or storage, and cannot navigate the top page.
    public const SANDBOX_CSP = 'sandbox allow-scripts allow-pointer-lock';

    // ----- tickets ---------------------------------------------------------

    /** @return array{0: string, 1: int} [token, expires_at unix timestamp] */
    public function issueTicket(int $userId, int $gameId): array
    {
        $expires = now()->getTimestamp() + self::TICKET_TTL_SECONDS;
        $payload = "{$userId}.{$gameId}.{$expires}";

        return ["{$payload}.".$this->sign($payload), $expires];
    }

    /** @return array{user_id: int, game_id: int}|null */
    public function verifyTicket(string $token): ?array
    {
        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }

        [, $userId, $gameId, $expires, $signature] = $m;
        if ((int) $expires < now()->getTimestamp() || ! hash_equals($this->sign("{$userId}.{$gameId}.{$expires}"), $signature)) {
            return null;
        }

        return ['user_id' => (int) $userId, 'game_id' => (int) $gameId];
    }

    private function sign(string $payload): string
    {
        // A key derived from APP_KEY, so a ticket cannot be reused as any other signature.
        return hash_hmac('sha256', $payload, hash('sha256', 'game-proxy|'.config('app.key'), true));
    }

    // ----- upstream addresses ----------------------------------------------

    /**
     * The folder the game's files live in, from the stored iframe_url. The
     * seeder (GameSeeder) is the only place a game's address is set.
     * Returns null when the address is not one the proxy can serve.
     */
    public function gameBase(Game $game): ?string
    {
        $parts = parse_url((string) $game->iframe_url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (! str_ends_with($path, '/')) {
            // "/minecraft" is a folder; "/game/index.html" is a file inside "/game/".
            $last = basename($path);
            $path = str_contains($last, '.') ? dirname($path).'/' : $path.'/';
        }

        return 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }

    /**
     * Full upstream URL for one file under the game's folder, or null when the
     * path tries to leave the folder.
     */
    public function assetUrl(Game $game, string $path, ?string $query = null): ?string
    {
        $base = $this->gameBase($game);
        if ($base === null) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment);
            if ($segment === '' || $decoded === '.') {
                continue;
            }
            // Reject ".." in any encoding, and anything that smuggles a separator.
            if ($decoded === '..' || str_contains($decoded, '/') || str_contains($decoded, '\\') || str_contains($decoded, "\0")) {
                return null;
            }
            $segments[] = $segment;
        }

        $url = $base.implode('/', $segments);
        if ($query !== null && $query !== '') {
            $url .= '?'.$query;
        }

        return $url;
    }

    public function supports(Game $game): bool
    {
        return $this->gameBase($game) !== null;
    }

    // ----- fetching --------------------------------------------------------

    /**
     * Fetch one upstream file for a player. Replays the player's cookies and
     * stores any cookies the response sets. Returns null on failure.
     *
     * @return array{status: int, type: string, body: string}|null (null = network failure)
     */
    public function fetch(Game $game, int $userId, string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $cacheKey = 'game_proxy:'.sha1($url);

        // Public files (no Set-Cookie in the response) are shared by every player.
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $jar = GamePlayerState::where('user_id', $userId)->where('game_id', $game->id)->value('cookies') ?? [];
        $cookieHeader = $this->cookieHeader($jar, (string) $host, (string) parse_url($url, PHP_URL_PATH), now()->getTimestamp());

        try {
            $response = Http::timeout(25)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => '*/*',
                    ...($cookieHeader !== '' ? ['Cookie' => $cookieHeader] : []),
                ])
                ->get($url);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $setCookies = $response->toPsrResponse()->getHeader('Set-Cookie');
        if (! empty($setCookies)) {
            $this->storeCookies($game->id, $userId, $setCookies, (string) $host);
        }

        $status = $response->status();
        if ($status !== 200) {
            // A missing file stays missing (the game handles it, as it did on the
            // game host); anything else is a failure. Nothing is cached.
            return ['status' => $status, 'type' => '', 'body' => ''];
        }

        $body = $response->body();
        $result = [
            'status' => 200,
            'type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'body' => $body,
        ];

        if (empty($setCookies) && strlen($body) <= self::MAX_CACHED_BODY_BYTES) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);
        }

        return $result;
    }

    // ----- localStorage ----------------------------------------------------

    /** The player's saved localStorage for this game (key => value). */
    public function storageFor(int $gameId, int $userId): array
    {
        $stored = GamePlayerState::where('user_id', $userId)->where('game_id', $gameId)->value('storage');

        return is_array($stored) ? $stored : [];
    }

    /**
     * Replace the player's saved localStorage. Returns false when the data is
     * not a flat map of strings or is over the size limits; nothing is saved then.
     */
    public function saveStorage(int $gameId, int $userId, mixed $data): bool
    {
        if (! is_array($data) || count($data) > self::MAX_STORAGE_KEYS) {
            return false;
        }

        $clean = [];
        $total = 0;
        foreach ($data as $key => $value) {
            if (! is_string($key) || ! is_string($value) || strlen($key) > 256 || strlen($value) > self::MAX_STORAGE_VALUE_BYTES) {
                return false;
            }
            $total += strlen($key) + strlen($value);
            if ($total > self::MAX_STORAGE_BYTES) {
                return false;
            }
            $clean[$key] = $value;
        }

        DB::transaction(function () use ($gameId, $userId, $clean) {
            GamePlayerState::query()->insertOrIgnore([
                'user_id' => $userId,
                'game_id' => $gameId,
                'cookies' => Crypt::encryptString('[]'),
                'storage' => Crypt::encryptString('{}'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $state = GamePlayerState::where('user_id', $userId)->where('game_id', $gameId)->lockForUpdate()->firstOrFail();
            $state->storage = $clean;
            $state->save();
        });

        return true;
    }

    // ----- cookie jar ------------------------------------------------------

    /** @param string[] $setCookieHeaders */
    public function storeCookies(int $gameId, int $userId, array $setCookieHeaders, string $host): void
    {
        $incoming = [];
        foreach ($setCookieHeaders as $header) {
            try {
                $cookie = Cookie::fromString($header);
            } catch (\Throwable) {
                continue;
            }

            $domain = ltrim((string) ($cookie->getDomain() ?: $host), '.');
            $incoming[] = [
                'name' => $cookie->getName(),
                'value' => (string) $cookie->getValue(),
                'domain' => $domain,
                'path' => $cookie->getPath() ?: '/',
                'expires' => $cookie->getExpiresTime() ?: null,
                'secure' => $cookie->isSecure(),
                'cleared' => $cookie->isCleared(),
            ];
        }

        if ($incoming === []) {
            return;
        }

        DB::transaction(function () use ($gameId, $userId, $incoming) {
            // Creating the row first avoids two players' first requests racing on the insert.
            GamePlayerState::query()->insertOrIgnore([
                'user_id' => $userId,
                'game_id' => $gameId,
                'cookies' => Crypt::encryptString('[]'),
                'storage' => Crypt::encryptString('{}'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jar = GamePlayerState::where('user_id', $userId)->where('game_id', $gameId)->lockForUpdate()->firstOrFail();
            $byKey = [];
            foreach ($jar->cookies ?? [] as $existing) {
                $byKey[$this->cookieKey($existing)] = $existing;
            }

            foreach ($incoming as $cookie) {
                $key = $this->cookieKey($cookie);
                if ($cookie['cleared'] || ($cookie['expires'] !== null && $cookie['expires'] <= now()->getTimestamp())) {
                    unset($byKey[$key]);

                    continue;
                }
                if (strlen($cookie['value']) > self::MAX_COOKIE_VALUE_BYTES) {
                    continue;
                }
                unset($cookie['cleared']);
                $byKey[$key] = $cookie;
            }

            $jar->cookies = array_slice(array_values($byKey), -self::MAX_COOKIES_PER_JAR);
            $jar->save();
        });
    }

    private function cookieKey(array $cookie): string
    {
        return $cookie['name'].'|'.$cookie['domain'].'|'.$cookie['path'];
    }

    /** The Cookie header for one upstream request, from the stored jar. */
    public function cookieHeader(array $jar, string $host, string $path, int $now): string
    {
        $pairs = [];
        foreach ($jar as $cookie) {
            if (($cookie['expires'] ?? null) !== null && $cookie['expires'] <= $now) {
                continue;
            }
            $domain = $cookie['domain'];
            if ($host !== $domain && ! str_ends_with($host, '.'.$domain)) {
                continue;
            }
            if (! str_starts_with($path ?: '/', $cookie['path'] ?: '/')) {
                continue;
            }
            $pairs[] = $cookie['name'].'='.$cookie['value'];
        }

        return implode('; ', $pairs);
    }

    // ----- the page --------------------------------------------------------

    /**
     * Make the game's page run through the proxy: remove the Famobi bootstrap
     * (ads), set the base so relative files come through the proxy, and put the
     * shim where the bootstrap was.
     */
    public function rewriteDocument(string $html, string $proxyBase, Game $game, array $storage = []): string
    {
        $base = $this->gameBase($game);
        // The player's saved localStorage, read by the shim before the game starts.
        $json = json_encode($storage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?: '{}';
        // Plain JavaScript: the bootstrap it replaces sits inside a <script> block,
        // so the shim cannot carry its own tags there.
        $shim = 'window.__CYO_GAME_STORAGE__ = '.$json.";\n".$this->shim();

        // The bootstrap is an IIFE that injects gameapi/v1.js and then the wrapper
        // redirect. Replace it in place; window.famobi_gameJS (the game's script
        // list) stays where it is, and the shim reads it.
        // Callbacks, not replacement strings: the shim carries the player's saved
        // values, and preg_replace would read "$1" or "\\" in them as syntax.
        $replaced = preg_replace_callback(
            '/\(function\s*\(\s*document\s*,\s*url\s*,\s*fgJS[\s\S]*?gameapi\/v1\.js[^;]*;/',
            fn () => "/* proxy: ads and API bootstrap removed */\n".$shim,
            $html,
            1,
            $count
        );

        if ($count === 0) {
            $replaced = preg_replace_callback('/<head([^>]*)>/i', fn ($m) => "<head{$m[1]}>\n<script>".$shim.'</script>', $html, 1);
        }

        // Drop every script that loads from another host (ad and analytics tags),
        // including the Famobi bootstrap if it appears in another form.
        $gameHost = parse_url($base, PHP_URL_HOST);
        $replaced = preg_replace_callback(
            '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*>\s*<\/script>/i',
            function ($m) use ($gameHost) {
                $src = html_entity_decode($m[1]);
                $host = parse_url($src, PHP_URL_HOST);

                return ($host === null || $host === $gameHost) ? $m[0] : '';
            },
            $replaced
        );

        $baseTag = '<base href="'.e($proxyBase).'">';
        $replaced = preg_replace_callback('/<head([^>]*)>/i', fn ($m) => "<head{$m[1]}>\n".$baseTag, $replaced, 1);

        return $replaced;
    }

    /** Browser-side shim. The game's script list comes from window.famobi_gameJS. */
    private function shim(): string
    {
        return <<<'JS'
(function () {
  // Setting document.domain throws in a sandboxed frame, and some games do it at
  // start-up. There is nothing for it to relax here, so the assignment is ignored.
  try {
    Object.defineProperty(document, 'domain', { configurable: true, get: function () { return ''; }, set: function () {} });
  } catch (e) {}

  // ---- storage ------------------------------------------------------------
  // The sandboxed frame has no real storage. localStorage is kept in memory,
  // seeded with the player's saved values, and changes are posted back to the
  // proxy, so the same progress is there on every device.
  var local = new Map(Object.entries(window.__CYO_GAME_STORAGE__ || {}));
  var saveUrl = new URL('_save', document.baseURI).href;
  var dirty = false;
  var timer = null;
  function flush() {
    if (!dirty) return;
    dirty = false;
    try {
      fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain' },
        body: JSON.stringify(Object.fromEntries(local)),
        keepalive: true,
        mode: 'no-cors'
      }).catch(function () {});
    } catch (e) {}
  }
  function changed() {
    dirty = true;
    clearTimeout(timer);
    timer = setTimeout(flush, 800);
  }
  window.addEventListener('pagehide', flush);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flush();
  });
  try { void window.localStorage.length; } catch (e) {
    Object.defineProperty(window, 'localStorage', { configurable: true, value: {
      getItem: function (k) { return local.has(String(k)) ? local.get(String(k)) : null; },
      setItem: function (k, v) { local.set(String(k), String(v)); changed(); },
      removeItem: function (k) { local.delete(String(k)); changed(); },
      clear: function () { local.clear(); changed(); },
      key: function (i) { return Array.from(local.keys())[i] ?? null; },
      get length() { return local.size; }
    } });
  }
  try { void window.sessionStorage.length; } catch (e) {
    var session = new Map();
    Object.defineProperty(window, 'sessionStorage', { configurable: true, value: {
      getItem: function (k) { return session.has(String(k)) ? session.get(String(k)) : null; },
      setItem: function (k, v) { session.set(String(k), String(v)); },
      removeItem: function (k) { session.delete(String(k)); },
      clear: function () { session.clear(); },
      key: function (i) { return Array.from(session.keys())[i] ?? null; },
      get length() { return session.size; }
    } });
  }

  // ---- Famobi API stand-in -------------------------------------------------
  // Games talk to window.famobi, which the Famobi wrapper and ad stack
  // provide. This page has neither: calls that would show an ad answer "no ad"
  // at once, handlers the game registers are kept, and the start is sent when
  // the game says it is ready (what the wrapper's Play step does).
  var handlers = {};
  var playerIsReady = false;
  var started = false;

  function startIfReady() {
    if (started || !playerIsReady || !handlers.startGame) return;
    started = true;
    setTimeout(function () { try { handlers.startGame(); } catch (e) {} }, 0);
  }

  // Anything not listed below resolves to another stub. A callback argument is
  // called with false, so nothing waits on an ad that never comes.
  function unknown() {
    var stub = function () {
      var cb = null;
      for (var i = 0; i < arguments.length; i++) if (typeof arguments[i] === 'function') cb = arguments[i];
      if (cb) { try { cb(false); } catch (e) {} }
      return unknown();
    };
    return new Proxy(stub, {
      get: function (t, k) {
        if (k === Symbol.toPrimitive) return function () { return ''; };
        // Called like a promise: resolve with nothing, so the game's .then() chains go on.
        if (k === 'then') return function (resolve) {
          if (typeof resolve === 'function') { try { resolve(undefined); } catch (e) {} }
          return unknown();
        };
        return unknown();
      }
    });
  }

  // Translations. The game asks famobi.__(key) for text; the text comes from the
  // en-US strings file in the game's folder, flattened to dotted keys
  // ("mainMenu.instructions"). Loaded once at start.
  var strings = {};
  function flatten(obj, prefix) {
    Object.keys(obj).forEach(function (k) {
      var v = obj[k];
      var key = prefix ? prefix + '.' + k : k;
      if (v && typeof v === 'object') flatten(v, key); else strings[key] = String(v);
    });
  }
  fetch(new URL('localized/en-US/strings.json', document.baseURI).href)
    .then(function (r) { return r.ok ? r.json() : {}; })
    .then(function (j) { flatten(j['en-US'] || j, ''); })
    .catch(function () {});

  var api = {
    onRequest: function (name, fn) {
      handlers[name] = fn;
      if (name === 'startGame') startIfReady();
    },
    playerReady: function () { playerIsReady = true; startIfReady(); },
    gameReady: function () {},
    setPreloadProgress: function () {},
    log: function () {},
    hasFeature: function () { return false; },
    getFeatureProperties: function () { return {}; },
    getVolume: function () { return 1; },
    moreGamesLink: function () { return ''; },
    // The "More games" button is Famobi's promotion, so it becomes a transparent pixel.
    getMoreGamesButtonImage: function () { return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; },
    // Resource paths and translation keys: see strings above.
    __: function (text) {
      var s = String(text);
      // A resource path (contains "/" or "text!") stays relative to the game's folder.
      if (s.indexOf('/') !== -1 || s.indexOf('text!') === 0) return s.replace(/localized\/$/, 'localized/en-US/');
      return Object.prototype.hasOwnProperty.call(strings, s) ? strings[s] : s;
    },
    localStorage: window.localStorage
  };
  window.famobi = new Proxy(api, { get: function (t, k) { return (k in t) ? t[k] : unknown(); } });
  window.famobi_analytics = unknown();
  // The game refers to its own window through this name.
  window.fenster = window;

  // Pause and resume with the tab, as the wrapper does.
  document.addEventListener('visibilitychange', function () {
    var h = document.visibilityState === 'hidden' ? handlers.pauseGameplay : handlers.resumeGameplay;
    if (h) { try { h(); } catch (e) {} }
  });

  // Games cannot open new tabs from the sandboxed frame.
  window.open = function () { return null; };

  // The sandboxed frame has an opaque origin, so the game's own images count as
  // cross-origin, and WebGL refuses to use them as textures. Ask for CORS on
  // every image the game sets; the proxy answers with Access-Control-Allow-Origin.
  var srcDesc = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'src');
  if (srcDesc && srcDesc.set) {
    Object.defineProperty(HTMLImageElement.prototype, 'src', {
      configurable: true,
      enumerable: srcDesc.enumerable,
      get: srcDesc.get,
      set: function (v) { if (!this.crossOrigin) this.crossOrigin = 'anonymous'; srcDesc.set.call(this, v); }
    });
  }

  // ---- the game's scripts, in order ---------------------------------------
  // Files load one after another, and the function entries run between them,
  // as the Famobi bootstrap ran them. A file that fails does not stop the rest.
  var list = (window.famobi_gameJS || []).slice();
  (function next() {
    if (!list.length) return;
    var entry = list.shift();
    if (typeof entry === 'function') {
      try { entry(); } catch (e) {}
      next();
      return;
    }
    var s = document.createElement('script');
    s.src = entry; // relative: resolves against the proxy page, so it goes through the proxy
    s.onload = next;
    s.onerror = next;
    document.head.appendChild(s);
  })();
})();
JS;
    }
}
