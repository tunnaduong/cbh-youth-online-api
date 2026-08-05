<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests from currently-banned accounts, even if they hold a
 * valid Sanctum token. Runs after auth:sanctum; silently no-ops for
 * guests so it never interferes with unauthenticated/optional-auth routes.
 */
class EnsureNotBanned
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'isCurrentlyBanned') && $user->isCurrentlyBanned()) {
            $bannedUntil = $user->banned_until;

            $message = $bannedUntil
                ? 'Tài khoản của bạn đã bị khóa đến ' . $bannedUntil->format('d/m/Y H:i') . '.'
                : 'Tài khoản của bạn đã bị khóa vĩnh viễn.';

            return response()->json([
                'message' => $message,
                'banned' => true,
                'ban_reason' => $user->ban_reason,
                'banned_until' => $bannedUntil,
            ], 403);
        }

        return $next($request);
    }
}
