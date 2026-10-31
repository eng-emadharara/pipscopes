<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every response (public site and admin panel).
 * nginx already sends X-Frame-Options and X-Content-Type-Options, so they aren't repeated here.
 */
class SecurityHeaders
{
    /**
     * Only rules that can't break the admin's own scripts (Settings → head_code), Google Analytics,
     * the Telegram widget or Filament: no plugins, no <base> hijacking, no framing by other sites.
     */
    public const CSP = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";

    public const PERMISSIONS = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=(), browsing-topics=()';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', self::PERMISSIONS, false);
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none', false);

        if (! $headers->has('Content-Security-Policy')) {
            // On a local http:// site, upgrading would point every asset at an https:// address that isn't served
            $headers->set('Content-Security-Policy', self::CSP.($request->isSecure() ? '; upgrade-insecure-requests' : ''));
        }

        // HTTPS only from now on, including subdomains; never on local http:// development
        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
