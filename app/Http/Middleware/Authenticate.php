<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Add custom message for saved posts page
        if ($request->is('saved*')) {
            session()->flash('message', 'Vui lòng đăng nhập để xem các bài viết đã lưu của bạn.');
        }

        // Build login URL with continue parameter
        $loginUrl = route('login');
        if (!$request->is('login') && !$request->is('register')) {
            $returnUrl = $request->fullUrl();
            $loginUrl .= '?continue=' . urlencode($returnUrl);
        }

        return $loginUrl;
    }
}
