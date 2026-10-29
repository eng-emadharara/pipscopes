<?php

namespace App\Support;

use App\Models\NotificationLog;
use App\Models\Review;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Who an announcement goes to, and on which channel. The audience is stored on the announcement:
 * channel (auto|mail|telegram|both), markets (ids), reviewed (any|yes|no), joined_days (null|int).
 * Members also need announcements switched on for that channel, and must be under the weekly cap.
 */
class AnnouncementAudience
{
    public const TYPE = 'announcements';

    public const CHANNEL_MODES = [
        'auto' => 'قناة واحدة لكل عضو (تيليجرام إن وُجد، وإلا البريد)',
        'both' => 'كل القنوات المتاحة للعضو',
        'mail' => 'البريد الإلكتروني فقط',
        'telegram' => 'تيليجرام فقط',
    ];

    public const REVIEWED = [
        'any' => 'الكل',
        'yes' => 'من كتبوا تقييماً منشوراً',
        'no' => 'من لم يكتبوا أي تقييم بعد',
    ];

    public const JOINED = [7 => 'آخر 7 أيام', 30 => 'آخر 30 يوماً', 90 => 'آخر 3 أشهر'];

    /** @param array<string, mixed> $audience */
    public static function query(array $audience): Builder
    {
        $markets = array_filter((array) ($audience['markets'] ?? []));

        return User::query()
            ->with('socialAccounts')
            ->when($markets, fn ($q) => $q->whereHas('reviews', fn ($r) => $r->where('status', Review::STATUS_APPROVED)
                ->whereHas('provider.markets', fn ($m) => $m->whereIn('markets.id', $markets))))
            ->when(($audience['reviewed'] ?? 'any') === 'yes', fn ($q) => $q->whereHas('reviews', fn ($r) => $r->where('status', Review::STATUS_APPROVED)))
            ->when(($audience['reviewed'] ?? 'any') === 'no', fn ($q) => $q->whereDoesntHave('reviews'))
            ->when($audience['joined_days'] ?? null, fn ($q, $days) => $q->where('created_at', '>=', now()->subDays((int) $days)));
    }

    /**
     * Each member who will get it, with their channels.
     *
     * @param  array<string, mixed>  $audience
     * @return LazyCollection<int, array{0: User, 1: list<string>}>
     */
    public static function recipients(array $audience): LazyCollection
    {
        $recent = self::recentCounts();
        $cap = NotificationPrefs::weeklyCap();

        return self::query($audience)->lazyById(200)
            ->reject(fn (User $user) => $cap > 0 && ($recent[$user->id] ?? 0) >= $cap)
            ->map(fn (User $user) => [$user, self::channelsFor($user, $audience['channel'] ?? 'auto')])
            ->filter(fn (array $row) => $row[1] !== []);
    }

    /** @return array{total: int, mail: int, telegram: int} */
    public static function estimate(array $audience): array
    {
        $counts = ['total' => 0, 'mail' => 0, 'telegram' => 0];
        foreach (self::recipients($audience) as [, $channels]) {
            $counts['total']++;
            $counts['mail'] += in_array('mail', $channels, true) ? 1 : 0;
            $counts['telegram'] += in_array(TelegramChannel::class, $channels, true) ? 1 : 0;
        }

        return $counts;
    }

    /** Laravel channels for one member under a channel mode */
    public static function channelsFor(User $user, string $mode): array
    {
        $channels = NotificationPrefs::channels($user, self::TYPE);
        $telegram = in_array(TelegramChannel::class, $channels, true);

        return match ($mode) {
            'mail' => array_values(array_intersect($channels, ['mail'])),
            'telegram' => $telegram ? [TelegramChannel::class] : [],
            'both' => $channels,
            // One message per member: Telegram is read more, so it wins when both are there
            default => $telegram ? [TelegramChannel::class] : $channels,
        };
    }

    /** user id => announcements received in the last 7 days */
    protected static function recentCounts(): array
    {
        return NotificationLog::query()
            ->where('type', self::TYPE)->where('status', 'sent')->whereNotNull('announcement_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, COUNT(DISTINCT announcement_id) as received')
            ->groupBy('user_id')
            ->pluck('received', 'user_id')->all();
    }
}
