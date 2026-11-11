<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Models\Review;
use App\Support\Launch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * During pre-launch, closes the parts of the public site that need the platform to be live
 * (browsing, search, reviews). The launch page, sign up and sign in, the add-provider form, content
 * pages and contact stay open, and so do the channels opened early. The admin panel is unaffected.
 */
class PrelaunchGate
{
    /** Public routes closed until launch */
    protected const CLOSED_ROUTES = [
        'providers.index', 'categories.show', 'providers.show', 'search.suggest', 'account-check', 'channel-check',
        'reviews.create', 'reviews.store', 'reviews.thanks', 'reviews.details', 'reviews.destroy',
        'reviews.helpful', 'reviews.report', 'impersonators.report',
        'reviews.show', 'share-images.provider', 'share-images.review',
    ];

    /** Of those, the ones that open for a channel opened early */
    protected const EARLY_ROUTES = [
        'providers.show', 'reviews.create', 'reviews.store', 'reviews.thanks', 'reviews.details', 'reviews.destroy',
        'reviews.helpful', 'reviews.report', 'impersonators.report',
        'reviews.show', 'share-images.provider', 'share-images.review',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Launch::appliesToVisitor() || ! $request->routeIs(...static::CLOSED_ROUTES)) {
            return $next($request);
        }

        if ($request->routeIs(...static::EARLY_ROUTES) && Launch::earlyOpen($this->channel($request))) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('الموقع في مرحلة ما قبل الإطلاق.')], 503)
            : redirect()->route('home');
    }

    /** The channel a request is about, directly or through one of its reviews */
    protected function channel(Request $request): ?Provider
    {
        $review = $request->route('review');
        $provider = $request->route('provider');

        return $provider instanceof Provider ? $provider : ($review instanceof Review ? $review->provider : null);
    }
}
