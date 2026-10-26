<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Support\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Live suggestions for the search boxes: matching providers and categories. */
    public function suggest(Request $request): JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        $words = SearchText::words($term);

        if (! $words || mb_strlen(implode('', $words)) < 2) {
            return response()->json(['providers' => [], 'categories' => [], 'impersonators' => []]);
        }

        // Someone checking an account that contacted them: say so loudly if it's a known fake
        $impersonators = Impersonator::matchingSearch($term)->map(fn (Impersonator $i) => [
            'handle' => $i->handle,
            'platform' => $i->platformLabel(),
            'message' => __('حساب منتحل: ينتحل :name وليس تابعاً له', ['name' => $i->provider->name]),
            'url' => route('providers.show', $i->provider).'#impersonators',
        ]);

        $providers = Provider::active()
            ->with('categories')
            ->search($term)
            ->orderByDesc('reviews_count')
            ->limit(6)
            ->get()
            ->map(fn (Provider $p) => [
                'name' => $p->name,
                'handle' => $p->telegramHandle(),
                'subtitle' => $p->tr('service_type'),
                'category' => $p->categories->first()?->tr('name'),
                'rating' => $p->reviews_count ? number_format($p->rating_avg, 1) : null,
                'reviews' => trans_choice(':count تقييم', $p->reviews_count),
                'verified' => $p->is_verified,
                'avatar' => $p->avatarUrl(),
                'initial' => $p->initials(),
                'url' => route('providers.show', $p),
            ]);

        // Few categories, so they are matched in PHP against both languages
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get()
            ->filter(function (Category $c) use ($words) {
                $text = SearchText::normalize(collect([$c->name, ...collect($c->translations ?? [])->pluck('name')])->join(' '));

                return collect($words)->every(fn ($w) => str_contains($text, $w));
            })
            ->take(3)
            ->map(fn (Category $c) => [
                'name' => $c->tr('name'),
                'icon' => $c->icon,
                'url' => route('categories.show', $c),
            ])
            ->values();

        return response()->json(['providers' => $providers, 'categories' => $categories, 'impersonators' => $impersonators]);
    }
}
