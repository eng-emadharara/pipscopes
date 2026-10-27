<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\TelegramSnapshot;
use Illuminate\Support\Carbon;

/**
 * A channel's Telegram numbers day by day, for the chart on its page. Snapshots are written only
 * when a number changed (App\Services\TelegramRefresh), so a missing day repeats the day before.
 */
class TelegramTrend
{
    /** Fewer known days than this and there's no line worth drawing */
    public const MIN_DAYS = 2;

    /**
     * @return list<array{date: Carbon, subscribers: ?int, avg_views: ?int, ratio: ?float}>
     */
    public static function lastDays(Provider $provider, int $days = 7): array
    {
        $first = now()->startOfDay()->subDays($days - 1);

        $rows = $provider->telegramSnapshots()
            ->where('period', TelegramSnapshot::DAY)
            ->where('date', '>=', $first->toDateString())
            ->get()
            ->keyBy('date');

        // The value carried into the first day: the latest row before the window
        $carry = $provider->telegramSnapshots()->where('date', '<', $first->toDateString())->orderByDesc('date')->first();

        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $first->copy()->addDays($i);
            $carry = $rows->get($date->toDateString(), $carry);

            $subscribers = $carry?->subscribers;
            $views = $carry?->avg_views;
            $points[] = [
                'date' => $date,
                'subscribers' => $subscribers,
                'avg_views' => $views,
                'ratio' => $subscribers && $views !== null ? round($views / $subscribers * 100, 2) : null,
            ];
        }

        return $points;
    }

    /** Whether the chart has enough known days to show. */
    public static function drawable(array $points): bool
    {
        return count(array_filter($points, fn (array $p) => $p['subscribers'] !== null)) >= self::MIN_DAYS;
    }

    public static function enabled(): bool
    {
        return (bool) Site::get('telegram_chart', true);
    }
}
