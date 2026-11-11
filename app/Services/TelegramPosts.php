<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\TelegramPost;
use App\Support\ChannelWatching;
use App\Support\PostKind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Follows a channel's posts from its public preview page (numbers only: no text or media).
 * Telegram numbers posts in order, so a post we saw that's missing while the posts around it
 * are still on the page was deleted. Each post is kept 45 days, then only the daily summary stays.
 */
class TelegramPosts
{
    public const KEEP_DAYS = 45;

    /** Channels that posted this recently are read every few hours, so deletions don't slip past */
    public const ACTIVE_DAYS = 3;

    public const ACTIVE_EVERY_HOURS = 4;

    /** @param  list<array{msg_id: int, posted_at: ?string, views: ?int, edited: bool}>  $items */
    public static function record(Provider $provider, array $items): void
    {
        if (! $items) {
            return;
        }

        $now = now();
        $ids = array_column($items, 'msg_id');

        DB::transaction(function () use ($provider, $items, $ids, $now) {
            $known = $provider->telegramPosts()->whereIn('msg_id', $ids)->get()->keyBy('msg_id');

            foreach ($items as $item) {
                $values = [
                    'posted_at' => $item['posted_at'] ? Carbon::parse($item['posted_at'])->setTimezone(config('app.timezone')) : null,
                    'views' => $item['views'],
                    'reactions' => $item['reactions'] ?? null,
                    // Sorted from the text as it reads now (an edit can change it); the text itself isn't kept
                    'kind' => PostKind::of($item['text'] ?? '', $item['photo'] ?? false),
                    'brokers' => implode(',', PostKind::brokers($item['text'] ?? '')) ?: null,
                ];

                if ($post = $known->get($item['msg_id'])) {
                    // Once edited, always edited; one that shows again wasn't deleted after all
                    $post->update($values + ['edited' => $post->edited || $item['edited'], 'deleted_at' => null]);
                } else {
                    $provider->telegramPosts()->create($values + [
                        'msg_id' => $item['msg_id'], 'edited' => $item['edited'], 'first_seen_at' => $now,
                    ]);
                }
            }

            // Seen before, inside the stretch the page covers now, and no longer on it
            $gone = $provider->telegramPosts()
                ->whereNull('deleted_at')
                ->whereBetween('msg_id', [min($ids), max($ids)])
                ->whereNotIn('msg_id', $ids);
            $deleted = (clone $gone)->pluck('kind');
            $gone->update(['deleted_at' => $now]);

            // Followers hear about it (ChannelWatching)
            ChannelWatching::deleted($provider, $deleted->count(), $deleted->filter(fn ($kind) => $kind === PostKind::SIGNAL)->count());
        });

        if (! $provider->telegram_posts_since) {
            $provider->forceFill(['telegram_posts_since' => $now])->saveQuietly();
        }
    }

    /**
     * The day's counts into telegram_post_days (kept for good), then posts older than 45 days go.
     * Run daily for yesterday (telegram:posts-daily); running it again for a day just rewrites it.
     */
    public static function summarize(Carbon $day): int
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $counts = fn (string $column, ?callable $where = null) => TelegramPost::query()
            ->whereBetween($column, [$from, $to])
            ->when($where, $where)
            ->selectRaw('provider_id, COUNT(*) as n')->groupBy('provider_id')->pluck('n', 'provider_id');

        $posted = $counts('posted_at');
        $deleted = $counts('deleted_at');
        $edited = $counts('posted_at', fn ($q) => $q->where('edited', true));

        foreach ($posted->keys()->merge($deleted->keys())->unique() as $providerId) {
            DB::table('telegram_post_days')->updateOrInsert(
                ['provider_id' => $providerId, 'date' => $from->toDateString()],
                ['posts' => $posted[$providerId] ?? 0, 'deleted' => $deleted[$providerId] ?? 0, 'edited' => $edited[$providerId] ?? 0],
            );
        }

        return TelegramPost::where('first_seen_at', '<', now()->subDays(self::KEEP_DAYS))
            ->where(fn ($q) => $q->whereNull('posted_at')->orWhere('posted_at', '<', now()->subDays(self::KEEP_DAYS)))
            ->delete();
    }
}
