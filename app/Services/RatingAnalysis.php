<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * Explains *why* a provider has its rating: star distribution, per-criterion
 * averages, the most cited pros/cons, the recent trend and a plain-language summary.
 */
class RatingAnalysis
{
    /** @var Collection<int, Review> */
    protected Collection $reviews;

    public function __construct(protected Provider $provider)
    {
        $this->reviews = $provider->approvedReviews()
            ->get(['id', 'rating', 'rating_accuracy', 'rating_transparency', 'rating_support', 'rating_value', 'pros', 'cons', 'created_at']);
    }

    public function total(): int
    {
        return $this->reviews->count();
    }

    /** @return array<int, array{count:int, percent:int}> keyed 5..1 */
    public function distribution(): array
    {
        $total = max($this->total(), 1);
        $out = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = $this->reviews->where('rating', $star)->count();
            $out[$star] = ['count' => $count, 'percent' => (int) round($count / $total * 100)];
        }

        return $out;
    }

    /** @return array<string, array{label:string, avg:float|null, count:int}> */
    public function criteria(): array
    {
        $out = [];
        foreach (__('trust.criteria') as $field => $label) {
            $values = $this->reviews->pluck($field)->filter();
            $out[$field] = [
                'label' => $label,
                'avg' => $values->isEmpty() ? null : round($values->avg(), 1),
                'count' => $values->count(),
            ];
        }

        return $out;
    }

    /** @return list<array{key:string, label:string, count:int, percent:int}> */
    public function topTags(string $type, int $limit = 5): array
    {
        $labels = __("trust.$type");
        $total = max($this->total(), 1);

        return $this->reviews
            ->flatMap(fn (Review $r) => $r->{$type} ?? [])
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->filter(fn ($count, $key) => isset($labels[$key]))
            ->map(fn ($count, $key) => [
                'key' => $key,
                'label' => $labels[$key],
                'count' => $count,
                'percent' => (int) round($count / $total * 100),
            ])
            ->values()
            ->all();
    }

    /** Average of the last 90 days compared to the overall average. */
    public function trend(): ?array
    {
        $recent = $this->reviews->where('created_at', '>=', now()->subDays(90));
        if ($recent->count() < 2 || $this->total() < 4) {
            return null;
        }

        $recentAvg = round($recent->avg('rating'), 1);
        $overall = round($this->reviews->avg('rating'), 1);
        $diff = round($recentAvg - $overall, 1);

        return [
            'recent_avg' => $recentAvg,
            'recent_count' => $recent->count(),
            'diff' => $diff,
            'direction' => $diff >= 0.3 ? 'up' : ($diff <= -0.3 ? 'down' : 'stable'),
        ];
    }

    /** @return list<string> Plain-language sentences explaining the rating. */
    public function summary(): array
    {
        $total = $this->total();
        if ($total === 0) {
            return [__('trust.analysis.empty')];
        }

        $lines = [];
        $avg = round($this->reviews->avg('rating'), 1);
        $dist = $this->distribution();

        $lines[] = __('trust.analysis.overall', [
            'avg' => $avg,
            'label' => Provider::ratingLabel($avg),
            'count' => $total,
            'positive' => $dist[5]['percent'] + $dist[4]['percent'],
            'negative' => $dist[1]['percent'] + $dist[2]['percent'],
        ]);

        $criteria = collect($this->criteria())->filter(fn ($c) => $c['avg'] !== null);
        if ($criteria->count() >= 2) {
            $best = $criteria->sortByDesc('avg')->first();
            $worst = $criteria->sortBy('avg')->first();
            $lines[] = $best['avg'] - $worst['avg'] >= 0.5
                ? __('trust.analysis.best_worst', ['best' => $best['label'], 'best_avg' => $best['avg'], 'worst' => $worst['label'], 'worst_avg' => $worst['avg']])
                : __('trust.analysis.balanced', ['best' => $best['label'], 'best_avg' => $best['avg']]);
        }

        $tagList = fn (array $tags) => collect($tags)
            ->map(fn ($t) => "{$t['label']} ({$t['percent']}%)")
            ->join(__('trust.analysis.list_separator'));

        if ($pros = $this->topTags('pros', 2)) {
            $lines[] = __('trust.analysis.top_pros', ['list' => $tagList($pros)]);
        }

        if ($cons = $this->topTags('cons', 2)) {
            $lines[] = __('trust.analysis.top_cons', ['list' => $tagList($cons)]);
        }

        $redFlags = collect($this->topTags('cons', 10))->whereIn('key', ['scam', 'deleted_trades', 'fake_results'])->sum('count');
        if ($redFlags > 0 && $redFlags / $total >= 0.2) {
            $lines[] = __('trust.analysis.red_flags');
        }

        if ($trend = $this->trend()) {
            $lines[] = __('trust.analysis.trend_'.$trend['direction'], ['avg' => $trend['recent_avg']]);
        }

        if ($total < 5) {
            $lines[] = __('trust.analysis.few_reviews');
        }

        return $lines;
    }
}
