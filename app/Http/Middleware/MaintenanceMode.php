<?php

namespace App\Http\Middleware;

use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While maintenance mode is on (Admin → وضع الصيانة), answers every public page with the
 * maintenance page and a 503, so search engines treat the downtime as temporary.
 */
class MaintenanceMode
{
    /** Always reachable: the admin panel (and the Livewire requests it makes), robots.txt and the language switch */
    protected const OPEN_PATHS = ['admin', 'admin/*', 'livewire/*', 'filament/*', 'robots.txt', 'lang/*'];

    public function handle(Request $request, Closure $next): Response
    {
        // ?bypass=<secret> remembers this browser, then drops the secret from the address bar
        if (filled($request->query('bypass')) && Maintenance::secret()
            && hash_equals(Maintenance::secret(), (string) $request->query('bypass'))) {
            return redirect()->to($request->fullUrlWithoutQuery('bypass'))
                ->withCookie(cookie(Maintenance::BYPASS_COOKIE, Maintenance::bypassToken(), 60 * 24 * 30));
        }

        // Admins can preview the page with ?preview=maintenance
        $preview = $request->query('preview') === 'maintenance' && $request->user()?->is_admin;

        if (! $preview && ($request->is(...static::OPEN_PATHS) || ! Maintenance::appliesTo($request))) {
            return $next($request);
        }

        $headers = ['Retry-After' => Maintenance::retryAfter(), 'Cache-Control' => 'no-store'];

        if ($request->expectsJson()) {
            return response()->json(['message' => __('الموقع تحت الصيانة حالياً، نعود قريباً.')], 503, $headers);
        }

        return response()->view('maintenance', [
            'endsAt' => Maintenance::endsAt(),
            'showCountdown' => Maintenance::showCountdown(),
            'endsAutomatically' => Maintenance::endsAutomatically(),
            'preview' => $preview,
        ], 503, $headers);
    }
}
