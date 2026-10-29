<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Members stay signed in as long as they keep coming back: once a day the remember cookie is sent again
 * with a new expiry (about a year, the most browsers keep a cookie). Admins are left out on purpose
 * (App\Http\Middleware\LimitAdminSession).
 */
class RenewRememberCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth()->guard('web');
        $user = $guard->user();

        if ($user && ! $user->is_admin && $request->session()->get('remember_renewed_at', 0) < now()->subDay()->timestamp) {
            $guard->renewRecaller();
            $request->session()->put('remember_renewed_at', now()->timestamp);
        }

        return $next($request);
    }
}
