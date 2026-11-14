<?php

/*
|--------------------------------------------------------------------------
| Game proxy
|--------------------------------------------------------------------------
|
| A small Go server (game-proxy/) that streams a third-party game through our
| own origin so the account's cookies and localStorage can be saved here and
| restored on another device, and so ad hosts can be blocked. Laravel signs the
| "play" link, stores the data and starts / watches the server; the server does
| only the proxying. See game-proxy/README.md.
|
*/

return [
  // Master switch. Off: no game is proxied, the clients use the plain iframe.
  'enabled' => (bool) env('GAME_PROXY_ENABLED', false),

  // Public address of the proxy. Must be a SEPARATE registrable domain from
  // chuyenbienhoa.com (not a subdomain): the login cookie is set for
  // .chuyenbienhoa.com, so any subdomain would hand it to the game's scripts.
  'public_url' => rtrim((string) env('GAME_PROXY_PUBLIC_URL', ''), '/'),

  // Shared secret: signs the play link, and authenticates the proxy's calls to
  // the internal save endpoints. Long and random.
  'secret' => (string) env('GAME_PROXY_SECRET', ''),

  // Where the Go server listens (local only; nginx forwards the public domain).
  'listen' => env('GAME_PROXY_LISTEN', '127.0.0.1:8099'),

  // The compiled server. Built in CI or on the host: see game-proxy/README.md.
  'binary' => env('GAME_PROXY_BINARY', base_path('game-proxy/bin/game-proxy')),

  // Start / restart it from the scheduler (game-proxy:ensure, every minute)
  // and on demand. Turn off when Supervisor or systemd runs game-proxy:start.
  'autostart' => (bool) env('GAME_PROXY_AUTOSTART', true),

  // How the proxy calls this API back (the internal save endpoints).
  'api_url' => rtrim((string) env('GAME_PROXY_API_URL', env('APP_URL', 'http://127.0.0.1')), '/'),

  // Sites allowed to put the proxied game in an iframe (frame-ancestors).
  'parent_origins' => array_filter(array_map('trim', explode(',', (string) env(
    'GAME_PROXY_PARENT_ORIGINS',
    'https://chuyenbienhoa.com,https://www.chuyenbienhoa.com'
  )))),

  // How long a play link (and the proxy session it starts) is valid.
  'link_ttl' => (int) env('GAME_PROXY_LINK_TTL', 6 * 3600),

  // Largest cookies + localStorage kept per account and game.
  'max_save_bytes' => (int) env('GAME_PROXY_MAX_SAVE_BYTES', 512 * 1024),

  // Extra blocked ad hosts, one per line (added to the built-in list).
  'blocklist_file' => env('GAME_PROXY_BLOCKLIST', base_path('game-proxy/blocklist.txt')),
];
