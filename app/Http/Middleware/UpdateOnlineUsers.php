<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to update the online status of users and guests.
 */
class UpdateOnlineUsers
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function handle(Request $request, Closure $next)
  {
    // Use OnlineUserController for tracking instead of duplicate code
    $controller = new \App\Http\Controllers\OnlineUserController();
    $controller->track($request);

    return $next($request);
  }

}
