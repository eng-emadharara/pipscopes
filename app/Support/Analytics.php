<?php

namespace App\Support;

use App\Models\Provider;

/**
 * Google Analytics events decided on the server. The layout hands them to resources/js/analytics.js,
 * which sends them with gtag; events from clicks are sent by the script itself ([data-track]).
 * Nothing is sent unless a Google Analytics ID is set (Site settings → الإحصائيات).
 *
 * Channel properties (channel_*) ride on every event of a channel's page; to report on them,
 * register them in Google Analytics under Admin → Custom definitions.
 */
class Analytics
{
    /** @var list<array{name: string, params: array<string, mixed>}> */
    protected static array $events = [];

    /** @var array<string, mixed> */
    protected static array $context = [];

    public static function enabled(): bool
    {
        return filled(Site::get('ga_id'));
    }

    /** An event for the page being rendered. */
    public static function event(string $name, array $params = []): void
    {
        // Without an ID the layout never collects them, so they must not pile up
        if (! static::enabled()) {
            return;
        }

        static::$events[] = ['name' => $name, 'params' => $params];
    }

    /** An event for the next page, for actions that end in a redirect (sending a review, a channel). */
    public static function flash(string $name, array $params = []): void
    {
        if (static::enabled()) {
            session()->flash('analytics_events', [...session('analytics_events', []), ['name' => $name, 'params' => $params]]);
        }
    }

    /** Properties added to every event sent from this page. */
    public static function context(array $params): void
    {
        if (! static::enabled()) {
            return;
        }

        static::$context = [...static::$context, ...$params];
    }

    /** @return array<string, string|int|null> */
    public static function channel(Provider $provider): array
    {
        $provider->loadMissing(['categories', 'markets']);
        $rating = (float) $provider->rating_avg;

        return [
            'channel_id' => $provider->id,
            'channel_name' => $provider->name,
            'channel_category' => $provider->categories->pluck('slug')->implode(','),
            'channel_market' => $provider->markets->pluck('slug')->implode(','),
            // Bands group channels in reports better than raw averages
            'channel_rating' => ! $provider->reviews_count ? 'none' : match (true) {
                $rating >= 4.5 => '4.5-5', $rating >= 4 => '4-4.5', $rating >= 3 => '3-4', $rating >= 2 => '2-3', default => '1-2',
            },
            'channel_reviews' => $provider->reviews_count,
            'channel_verified' => $provider->is_verified ? 'yes' : 'no',
        ];
    }

    /**
     * Read once by the layout; what was collected is cleared so it never leaks into a later
     * request served by the same process (tests, long-running workers).
     *
     * @return array{events: list<array{name: string, params: array<string, mixed>}>, context: array<string, mixed>}
     */
    public static function payload(): array
    {
        $payload = [
            'events' => [...session('analytics_events', []), ...static::$events],
            'context' => static::$context,
        ];
        static::$events = [];
        static::$context = [];

        return $payload;
    }
}
