<?php

namespace App\Http\Middleware;

use App\Services\DeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the device details on the Sanctum token making the request up to
 * date (platform, app version, device name and model), so the user can tell
 * their logged-in devices apart. Runs after auth:sanctum and only writes
 * when something actually changed, e.g. after an app update.
 */
class TrackDeviceSession
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
        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            try {
            $details = DeviceSessionService::detailsFromRequest($request);

            // App versions from before these headers existed: the user agent
            // is the only hint about what the device is.
            if (!isset($details['device_name']) && $token->device_name === null && $request->userAgent()) {
                $details['device_name'] = Str::limit($request->userAgent(), 250, '');
            }

            $token->forceFill($details);

            if ($token->isDirty()) {
                $token->save();
            }
            } catch (\Throwable $e) {
                // Bookkeeping only (e.g. the columns don't exist yet because the
                // migration hasn't run): never fail the request over it.
            }
        }

        return $next($request);
    }
}
