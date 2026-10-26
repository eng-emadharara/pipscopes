<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Channels whose reviewers report serious warning signs. Same rule as the "⚠️" line in the
 * rating analysis (RatingAnalysis): at least 20% of reviews mention one of these.
 */
class RedFlags
{
    public const CONS = ['scam', 'deleted_trades', 'fake_results'];

    public const MIN_SHARE = 0.2;

    /** Fewer reviews than this is too thin to flag a channel on the homepage */
    public const MIN_REVIEWS = 3;

    protected const CACHE_KEY = 'home.red_flags';

    /** Called whenever a review is saved or deleted */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, array{provider: Provider, flagged: int, share: float, cons: list<string>}>
     */
    public static function providers(int $limit = 4): Collection
    {
        $ids = Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $counts = [];

            Review::where('status', Review::STATUS_APPROVED)
                ->where(fn ($q) => collect(self::CONS)->each(fn ($con) => $q->orWhere('cons', 'like', '%"'.$con.'"%')))
                ->select(['id', 'provider_id', 'cons'])
                ->lazyById()
                ->each(function (Review $review) use (&$counts) {
                    $hits = array_values(array_intersect($review->cons ?? [], self::CONS));
                    if (! $hits) {
                        return;
                    }
                    $counts[$review->provider_id]['flagged'] = ($counts[$review->provider_id]['flagged'] ?? 0) + 1;
                    foreach ($hits as $con) {
                        $counts[$review->provider_id]['cons'][$con] = ($counts[$review->provider_id]['cons'][$con] ?? 0) + 1;
                    }
                });

            return $counts;
        });

        if (! $ids) {
            return collect();
        }

        return Provider::active()
            ->whereIn('id', array_keys($ids))
            ->where('reviews_count', '>=', self::MIN_REVIEWS)
            ->get()
            ->map(function (Provider $provider) use ($ids) {
                $flagged = $ids[$provider->id]['flagged'];
                arsort($ids[$provider->id]['cons']);

                return [
                    'provider' => $provider,
                    'flagged' => $flagged,
                    'share' => $flagged / max(1, $provider->reviews_count),
                    'cons' => array_keys($ids[$provider->id]['cons']),
                ];
            })
            ->filter(fn (array $row) => $row['share'] >= self::MIN_SHARE)
            ->sortByDesc(fn (array $row) => [$row['share'], $row['flagged']])
            ->take($limit)
            ->values();
    }
}
