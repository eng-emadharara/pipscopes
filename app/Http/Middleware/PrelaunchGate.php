<?php

namespace App\Http\Middleware;

use App\Support\Launch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * During pre-launch, closes the parts of the public site that need the platform to be live
 * (browsing, reviews, sign up). The launch page, the add-provider form, content pages and
 * contact stay open; the admin panel is unaffected.
 */
class PrelaunchGate
{
    /** Public routes closed until launch */
    protected const CLOSED_ROUTES = [
        'providers.index', 'categories.show', 'providers.show', 'search.suggest', 'account-check',
        'reviews.create', 'reviews.store', 'reviews.destroy', 'reviews.helpful', 'reviews.report', 'impersonators.report',
        'reviews.show', 'share-images.provider', 'share-images.review',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Launch::appliesToVisitor()) {
            return $next($request);
        }

        $closed = $request->routeIs(...static::CLOSED_ROUTES) || $request->is('login', 'register');

        if (! $closed) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('الموقع في مرحلة ما قبل الإطلاق.')], 503)
            : redirect()->route('home');
    }
}
