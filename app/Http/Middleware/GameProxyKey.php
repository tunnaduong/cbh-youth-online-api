<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards the internal endpoints only the game proxy calls: the request must
 * carry the shared secret in X-Game-Proxy-Key.
 */
class GameProxyKey
{
  public function handle(Request $request, Closure $next)
  {
    $secret = (string) config('gameproxy.secret');
    $given = (string) $request->header('X-Game-Proxy-Key', '');

    if (!config('gameproxy.enabled') || $secret === '' || !hash_equals($secret, $given)) {
      abort(404);
    }

    return $next($request);
  }
}
