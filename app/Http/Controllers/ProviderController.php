<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Impersonator;
use App\Models\Market;
use App\Models\Provider;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\ReviewVote;
use App\Services\RatingAnalysis;
use App\Support\Analytics;
use App\Support\OwnerPortal;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /** Page sizes offered on the browse page; the first is the default */
    public const PER_PAGE_OPTIONS = [12, 24, 48];

    public function index(Request $request, ?Category $category = null)
    {
        $query = Provider::active()->with(['categories', 'markets']);

        // A provider shows up under every category it belongs to
        if ($category) {
            $query->whereHas('categories', fn ($q) => $q->whereKey($category->id));
        } elseif ($request->filled('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $request->string('category')));
        }

        // Searches name, description, social links, categories and translations (see Provider::scopeSearch)
        if ($search = trim((string) $request->input('q'))) {
            $query->search($search, rankByName: ! $request->filled('sort'));
        }

        // Providers covering any of the selected markets
        $selectedMarkets = array_values(array_filter((array) $request->input('markets', []), 'is_string'));
        if ($selectedMarkets) {
            $query->whereHas('markets', fn ($q) => $q->whereIn('slug', $selectedMarkets));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_avg', '>=', (float) $request->input('min_rating'));
        }

        if ($request->boolean('verified')) {
            $query->where('is_verified', true);
        }

        match ($request->input('sort')) {
            'reviews' => $query->orderByDesc('reviews_count'),
            'newest' => $query->latest(),
            'lowest' => $query->where('reviews_count', '>', 0)->orderBy('rating_avg'),
            default => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
        };

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : self::PER_PAGE_OPTIONS[0];

        $providers = $query->paginate($perPage)->onEachSide(1)->withQueryString();

        // A page number past the end (e.g. after narrowing the filters) goes to the last page
        if ($providers->isEmpty() && $providers->currentPage() > 1 && $providers->lastPage() >= 1) {
            return redirect()->to($providers->url($providers->lastPage()));
        }

        // GA's standard search event; a zero count shows channels people look for that aren't listed yet
        if (filled($search ?? null) && $providers->currentPage() === 1) {
            Analytics::event('search', ['search_term' => $search, 'results_count' => $providers->total()]);
        }

        return view('providers.index', [
            'impersonatorWarnings' => $search ? Impersonator::matchingSearch($search) : collect(),
            'providers' => $providers,
            'perPage' => $perPage,
            'category' => $category,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'markets' => Market::active()->get(),
            'selectedMarkets' => $selectedMarkets,
        ]);
    }

    /** One review on its own page: the link people share, with the review's own preview image. */
    public function review(Request $request, Review $review)
    {
        abort_unless($review->status === Review::STATUS_APPROVED && $review->provider?->is_active, 404);

        $voterKey = Review::voterKey($request);

        return view('reviews.show', [
            'review' => $review->load(['provider', 'user']),
            'provider' => $review->provider,
            'voted' => $review->votes()->where('voter_key', $voterKey)->exists(),
            'reported' => $review->reports()->where('voter_key', $voterKey)->exists(),
        ]);
    }

    public function show(Request $request, Provider $provider)
    {
        abort_unless($provider->is_active, 404);

        // Every event on this page carries the channel's properties (category, market, rating band…)
        Analytics::context(Analytics::channel($provider));
        Analytics::event('view_channel');

        $provider->load('categories');

        $sort = array_key_exists($request->input('sort'), __('trust.review_sorts')) ? $request->input('sort') : 'helpful';

        // The channel's own owner manages the page in place: replies and the edit window
        $managing = OwnerPortal::manages($provider);

        $reviews = $provider->approvedReviews()
            ->with('user')
            ->when($request->filled('stars'), fn ($q) => $q->where('rating', (int) $request->input('stars')))
            ->when($managing && $request->input('replied') === '0', fn ($q) => $q->whereNull('provider_reply'))
            ->tap(fn ($q) => match ($sort) {
                'newest' => $q->latest(),
                'highest' => $q->orderByDesc('rating')->latest(),
                'lowest' => $q->orderBy('rating')->latest(),
                default => $q->orderByDesc('helpful_count')->latest(),
            })
            ->paginate(10)
            ->withQueryString();

        $userReview = $request->user()
            ? $provider->reviews()->where('user_id', $request->user()->id)->first()
            : null;

        // Which reviews on this page the current visitor already marked helpful / reported
        $voterKey = Review::voterKey($request);
        $reviewIds = $reviews->pluck('id');

        return view('providers.show', [
            'provider' => $provider,
            'reviews' => $reviews,
            'sort' => $sort,
            'analysis' => new RatingAnalysis($provider),
            'userReview' => $userReview,
            'impersonators' => $provider->impersonators()->confirmed()->orderByDesc('reports_count')->get(),
            'managing' => $managing,
            'unanswered' => $managing ? $provider->approvedReviews()->whereNull('provider_reply')->count() : 0,
            'markets' => $managing ? Market::active()->get() : collect(),
            'votedIds' => ReviewVote::where('voter_key', $voterKey)->whereIn('review_id', $reviewIds)->pluck('review_id')->all(),
            'reportedIds' => ReviewReport::where('voter_key', $voterKey)->whereIn('review_id', $reviewIds)->pluck('review_id')->all(),
        ]);
    }
}
