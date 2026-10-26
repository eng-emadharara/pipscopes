<?php

namespace App\Http\Controllers;

use App\Models\Category;
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

        $sections = HomeSections::all();

        return view('home', [
            'sections' => $sections,
            'alerts' => $sections['alerts'] ? [
                'impersonators' => Impersonator::confirmed()
                    ->whereHas('provider', fn ($q) => $q->active())
                    ->with('provider')
                    ->latest('updated_at')
                    ->take(5)
                    ->get(),
                'flagged' => RedFlags::providers(5),
            ] : null,
            'categories' => Category::where('is_active', true)
                ->withCount(['providers' => fn ($q) => $q->active()])
                ->orderBy('sort_order')
                ->get(),
            'featured' => Provider::active()->with(['categories', 'markets'])
                ->orderByDesc('is_featured')
                ->orderByDesc('rating_avg')
                ->orderByDesc('reviews_count')
                ->take(8)
                ->get(),
            'latestReviews' => Review::where('status', Review::STATUS_APPROVED)
                ->whereHas('provider', fn ($q) => $q->active())
                ->with(['provider', 'user'])
                ->latest()
                ->take(6)
                ->get(),
            'stats' => [
                'providers' => Provider::active()->count(),
                'reviews' => Review::where('status', Review::STATUS_APPROVED)->count(),
            ],
        ]);
    }
}
