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
     * Days after the channel's last check have no numbers: carrying the last ones into them would show
     * a reading nobody took. "unchanged" marks a checked day that repeats the day before.
     *
     * @return list<array{date: Carbon, subscribers: ?int, avg_views: ?int, ratio: ?float, unchanged: bool}>
     */
    public static function lastDays(Provider $provider, int $days = 7): array
    {
        $first = now()->startOfDay()->subDays($days - 1);
        // Snapshot dates and the check time are both in the app time zone
        $checkedThrough = $provider->telegram_checked_at?->toDateString();

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
            $row = $rows->get($date->toDateString());
            $carry = $row ?? $carry;
            $notCheckedYet = $checkedThrough && $date->toDateString() > $checkedThrough;
            $reading = $notCheckedYet ? null : $carry;

            $subscribers = $reading?->subscribers;
            $views = $reading?->avg_views;
            $points[] = [
                'date' => $date,
                'subscribers' => $subscribers,
                'avg_views' => $views,
                'ratio' => $subscribers && $views !== null ? round($views / $subscribers * 100, 2) : null,
                'unchanged' => $reading !== null && ! $row,
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
