<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\TelegramSnapshot;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps each channel's subscriber count and average views current (run by telegram:refresh)
 * and records their history in telegram_snapshots.
 */
class TelegramRefresh
{
    /** After this many failed reads in a row a channel is only retried weekly */
    public const GIVE_UP_AFTER = 7;

    /**
     * Channels due a refresh, oldest first: not read for a day, or for a week once they keep
     * failing (gone private, deleted, renamed) so they don't take every run's slots.
     */
    public static function due(): Builder
    {
        return Provider::active()
            ->whereNotNull('telegram_url')
            ->where(fn ($q) => $q->whereNull('telegram_checked_at')
                ->orWhere(fn ($q) => $q->where('telegram_failures', '<', self::GIVE_UP_AFTER)->where('telegram_checked_at', '<=', now()->subHours(23)))
                ->orWhere(fn ($q) => $q->where('telegram_failures', '>=', self::GIVE_UP_AFTER)->where('telegram_checked_at', '<=', now()->subDays(7))))
            ->orderByRaw('telegram_checked_at IS NOT NULL')
            ->orderBy('telegram_checked_at');
    }

    /** @return string the outcome (TelegramChannel::CHANNEL on success) */
    public static function refresh(Provider $provider): string
    {
        $channel = TelegramChannel::read($provider->telegram_url, fresh: true);
        $status = $channel['status'];

        if ($status !== TelegramChannel::CHANNEL) {
            // Keep the last good numbers; just note what happened
            $provider->forceFill([
                'telegram_checked_at' => now(),
                'telegram_status' => $status,
                'telegram_failures' => $provider->telegram_failures + 1,
            ])->saveQuietly();

            return $status;
        }

        $stats = $channel['stats'];
        $data = [
            'username' => $channel['username'],
            'subscribers' => $channel['subscribers'],
            'verified' => $channel['verified'],
            ...$stats,
        ];

        // No photo yet (added by hand without one): take the channel's
        $avatar = $provider->avatar ?: (TelegramChannel::storePhoto($channel['photo_url'], $channel['username']) ?? $channel['photo_url']);

        $provider->forceFill([
            'telegram_members' => $channel['subscribers'] ?? $provider->telegram_members,
            'telegram_data' => $data,
            'avatar' => $avatar,
            'telegram_checked_at' => now(),
            'telegram_status' => $status,
            'telegram_failures' => 0,
        ])->saveQuietly();

        self::record($provider, $channel['subscribers'], $stats['avg_views'] ?? null);

        return $status;
    }

    /** Today's row, written only when a number moved since the last one (gaps mean "unchanged"). */
    public static function record(Provider $provider, ?int $subscribers, ?int $avgViews): void
    {
        $last = $provider->telegramSnapshots()->orderByDesc('date')->first();
        $today = now()->toDateString();

        if ($last && $last->subscribers === $subscribers && $last->avg_views === $avgViews && $last->date !== $today) {
            return;
        }

        TelegramSnapshot::updateOrCreate(
            ['provider_id' => $provider->id, 'period' => TelegramSnapshot::DAY, 'date' => $today],
            ['subscribers' => $subscribers, 'avg_views' => $avgViews],
        );
    }
}
