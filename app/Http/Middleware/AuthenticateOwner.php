<?php

namespace App\Http\Middleware;

use App\Support\OwnerPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Requires a signed-in channel owner with an active account (the portal itself is checked by EnsureOwnerPortalEnabled). */
class AuthenticateOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! OwnerPortal::owner()) {
            return $request->expectsJson()
                ? response()->json(['message' => __('سجّل الدخول كصاحب قناة أولاً.')], 401)
                : redirect()->route('owner.login');
        }

        return $next($request);
    }
}
