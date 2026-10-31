<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomePick;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;
use App\Support\HomeSections;
use App\Support\Launch;
use App\Support\RedFlags;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        // Admins can preview the launch page with ?preview=prelaunch
        $preview = $request->query('preview') === 'prelaunch' && $request->user()?->is_admin;

        if (Launch::appliesToVisitor() || $preview) {
            return view('prelaunch', [
                'launchAt' => Launch::launchAt(),
                'showCountdown' => Launch::showCountdown(),
                'opensAutomatically' => Launch::opensAutomatically(),
            ]);
        }

        // Only the sections that are switched on, in the admin's order; each loads only its own data
        $layout = array_values(array_filter(HomeSections::layout(), fn ($s) => $s['enabled']));
        $on = array_column($layout, 'limit', 'key');
        $limit = fn (string $key) => $on[$key] ?? HomeSections::SECTIONS[$key][2];

        return view('home', [
            'layout' => $layout,
            'categories' => array_key_exists('categories', $on)
                ? Category::where('is_active', true)->withCount(['providers' => fn ($q) => $q->active()])->orderBy('sort_order')->get()
                : collect(),
            'picks' => array_key_exists('picks', $on)
                ? HomePick::showing()->with(['provider.categories', 'provider.markets'])->get()
                : collect(),
            'featured' => array_key_exists('top_rated', $on)
                ? Provider::active()->with(['categories', 'markets'])
                    ->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('reviews_count')
                    ->take($limit('top_rated'))->get()
                : collect(),
            'alerts' => array_key_exists('alerts', $on) ? [
                'impersonators' => Impersonator::confirmed()
                    ->whereHas('provider', fn ($q) => $q->active())
                    ->with('provider')
                    ->latest('updated_at')
                    ->take(5)
                    ->get(),
                'flagged' => RedFlags::providers(5),
            ] : null,
            'latestReviews' => array_key_exists('latest_reviews', $on)
                ? Review::where('status', Review::STATUS_APPROVED)
                    ->whereHas('provider', fn ($q) => $q->active())
                    ->with(['provider', 'user'])
                    ->latest()
                    ->take($limit('latest_reviews'))
                    ->get()
                : collect(),
            'stats' => [
                'providers' => Provider::active()->count(),
                'reviews' => Review::where('status', Review::STATUS_APPROVED)->count(),
                'impersonators' => Impersonator::confirmed()->whereHas('provider', fn ($q) => $q->active())->count(),
            ],
            // Hero: the most reviewed channels as quick searches
            'popular' => Provider::active()->where('reviews_count', '>', 0)
                ->orderByDesc('reviews_count')->orderByDesc('rating_avg')
                ->take(4)
                ->get(['id', 'name', 'slug']),
        ]);
    }
}
