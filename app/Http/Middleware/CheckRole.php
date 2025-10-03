<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle an incoming request and check if the user has a specific role.
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role The role to check for.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user() || $request->user()->role !== $role) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthorized. Requires ' . $role . ' role.'], 403);
            }
            return redirect()->route('login')->with('error', 'Unauthorized. Requires ' . $role . ' role.');
        }

        return $next($request);
    }
}
