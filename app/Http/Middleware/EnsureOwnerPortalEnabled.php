<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Closes the whole owner portal (login page included) while it's switched off in admin settings. */
class EnsureOwnerPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get('owner_portal_enabled', false)) {
            return $next($request);
        }

        // Sign out anyone still logged in from before the portal was switched off
        Auth::guard('owner')->logout();

        return response()->view('owner-portal-disabled', status: 503);
    }
}
