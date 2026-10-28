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

    /** "At least N reviews" choices of the rating filter */
    public const MIN_REVIEWS_OPTIONS = [5, 20, 50];

    public function index(Request $request, ?Category $category = null)
    {
        // "What does it offer?": any of the chosen categories. /category/{slug} (the homepage cards)
        // and the older ?category= link arrive with theirs already chosen.
        $selectedCategories = array_values(array_unique(array_filter([
            ...array_filter((array) $request->input('categories', []), 'is_string'),
            is_string($request->input('category')) ? $request->input('category') : null,
            $category?->slug,
        ])));
        // Searches name, description, social links, categories and translations (see Provider::scopeSearch)
        $search = trim((string) $request->input('q'));
        // Providers covering any of the selected markets
        $selectedMarkets = array_values(array_filter((array) $request->input('markets', []), 'is_string'));
        $minRating = $request->filled('min_rating') ? (float) $request->input('min_rating') : null;
        // A 5.0 from a single review means little: visitors can ask for a minimum number of reviews
        $minReviews = in_array((int) $request->input('min_reviews'), self::MIN_REVIEWS_OPTIONS, true) ? (int) $request->input('min_reviews') : null;
        $verified = $request->boolean('verified');

        // Every filter except $except: the count beside each option is what choosing it would show
        $filter = function ($q, ?string $except = null, bool $rankByName = false) use ($selectedCategories, $search, $selectedMarkets, $minRating, $minReviews, $verified) {
            $q->active();
            if ($selectedCategories && $except !== 'categories') {
                $q->whereHas('categories', fn ($c) => $c->whereIn('slug', $selectedCategories));
            }
            if ($search !== '') {
                $q->search($search, rankByName: $rankByName);
            }
            if ($selectedMarkets && $except !== 'markets') {
                $q->whereHas('markets', fn ($m) => $m->whereIn('slug', $selectedMarkets));
            }
            if ($minRating !== null && $except !== 'rating') {
                $q->where('rating_avg', '>=', $minRating);
            }
            if ($minReviews !== null && $except !== 'reviews') {
                $q->where('reviews_count', '>=', $minReviews);
            }
            if ($verified && $except !== 'verified') {
                $q->where('is_verified', true);
            }

            return $q;
        };

        $query = $filter(Provider::query(), rankByName: ! $request->filled('sort'))->with(['categories', 'markets']);

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

        // Each option with its count (providers_count), given the other filters
        $categories = Category::where('is_active', true)->orderBy('sort_order')
            ->withCount(['providers' => fn ($q) => $filter($q, 'categories')])->get();
        $markets = Market::active()->withCount(['providers' => fn ($q) => $filter($q, 'markets')])->get();
        $ratingCounts = collect(['' => null, '3' => 3, '4' => 4, '4.5' => 4.5])
            ->map(fn ($min) => $filter(Provider::query(), 'rating')->when($min, fn ($q) => $q->where('rating_avg', '>=', $min))->count());
        $reviewCounts = collect(['' => null] + array_combine(self::MIN_REVIEWS_OPTIONS, self::MIN_REVIEWS_OPTIONS))
            ->map(fn ($min) => $filter(Provider::query(), 'reviews')->when($min, fn ($q) => $q->where('reviews_count', '>=', $min))->count());
        $verifiedCount = $filter(Provider::query(), 'verified')->where('is_verified', true)->count();
        // Total with no category chosen, for the "All" tab
        $allCategoriesCount = $selectedCategories ? $filter(Provider::query(), 'categories')->count() : $providers->total();

        // One chip per active filter above the results, each removable on its own
        $activeFilters = [
            ...$categories->whereIn('slug', $selectedCategories)->map(fn ($c) => ['label' => $c->icon.' '.$c->tr('name'), 'name' => 'categories[]', 'value' => $c->slug]),
            ...$markets->whereIn('slug', $selectedMarkets)->map(fn ($m) => ['label' => $m->icon.' '.$m->tr('name'), 'name' => 'markets[]', 'value' => $m->slug]),
            ...($request->filled('min_rating') ? [['label' => __(':n+ نجوم', ['n' => (float) $request->input('min_rating')]), 'name' => 'min_rating', 'value' => (string) $request->input('min_rating')]] : []),
            ...($minReviews ? [['label' => __(':n+ تقييمات', ['n' => $minReviews]), 'name' => 'min_reviews', 'value' => (string) $minReviews]] : []),
            ...($request->boolean('verified') ? [['label' => __('الموثّقون فقط'), 'name' => 'verified', 'value' => '1']] : []),
        ];

        return view('providers.index', [
            'impersonatorWarnings' => $search ? Impersonator::matchingSearch($search) : collect(),
            'providers' => $providers,
            'perPage' => $perPage,
            // The page is titled after the category when exactly one is chosen
            'category' => count($selectedCategories) === 1 ? ($category ?? $categories->firstWhere('slug', $selectedCategories[0])) : null,
            'categories' => $categories,
            'markets' => $markets,
            'selectedCategories' => $selectedCategories,
            'selectedMarkets' => $selectedMarkets,
            'activeFilters' => array_values($activeFilters),
            'ratingCounts' => $ratingCounts,
            'minReviews' => $minReviews,
            'reviewCounts' => $reviewCounts,
            'verifiedCount' => $verifiedCount,
            'allCategoriesCount' => $allCategoriesCount,
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
