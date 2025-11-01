<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to optionally authenticate a user via Sanctum.
 *
 * This allows a request to proceed even if the user is not authenticated,
 * but if a valid Sanctum token is provided, the user will be authenticated.
 */
class OptionalAuthSanctum
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
        // Try to authenticate if token is provided
        if ($request->bearerToken()) {
            try {
                // First try Sanctum guard
                $user = Auth::guard('sanctum')->user();
                if ($user) {
                    // Set user to default guard so Auth::user() works
                    Auth::setUser($user);
                    // Also ensure Sanctum guard has the user
                    Auth::guard('sanctum')->setUser($user);
                }
            } catch (\Exception $e) {
                // If token is invalid, just continue without authentication
                // This allows the request to proceed as a guest
            }
        }

        return $next($request);
    }
}
