<?php

namespace App\Support;

use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Three plain indicators from a channel's posts (App\Services\TelegramPosts), for its page:
 * engagement (views ÷ subscribers), posts deleted lately, and how active it is.
 * Each describes a number and never accuses; an indicator without enough data isn't shown at all.
 */
class TelegramInsights
{
    public const KEYS = ['engagement', 'deleted', 'activity'];

    public const WINDOW_DAYS = 30;

    /** Deletions count only after this long following the channel (before it, "still watching") */
    public const DELETED_AFTER_DAYS = 14;

    public const ENGAGEMENT_MIN_SUBSCRIBERS = 500;

    public const ENGAGEMENT_MIN_POSTS = 10;

    /**
     * @return array<string, array{key: string, level: string, value: string, label: string, status: string, note: string}>
     *         level: good | warn | bad | pending
     */
    public static function for(Provider $provider): array
    {
        if (! $provider->telegram_posts_since || ! ($provider->telegram_data['preview_enabled'] ?? false)) {
            return [];
        }

        $hidden = $provider->telegram_hidden_insights ?? [];
        $since = now()->subDays(self::WINDOW_DAYS);
        $posts = $provider->telegramPosts()->where('posted_at', '>=', $since)->get(['msg_id', 'posted_at', 'views', 'deleted_at']);

        return array_filter([
            'engagement' => in_array('engagement', $hidden, true) ? null : self::engagement($provider, $posts),
            'deleted' => in_array('deleted', $hidden, true) ? null : self::deleted($provider),
            'activity' => in_array('activity', $hidden, true) ? null : self::activity($provider, $posts),
        ]);
    }

    /** The day the deleted-posts count shows: two weeks after we started following the channel (null before its first read) */
    public static function deletedReadyAt(Provider $provider): ?Carbon
    {
        return $provider->telegram_posts_since?->copy()->addDays(self::DELETED_AFTER_DAYS)->startOfDay();
    }

    /** Average views of posts at least a day old (fresh ones are still gathering views) ÷ subscribers */
    protected static function engagement(Provider $provider, Collection $posts): ?array
    {
        $subscribers = (int) $provider->telegram_members;
        $views = $posts->filter(fn ($p) => $p->views !== null && ! $p->deleted_at && $p->posted_at <= now()->subDay())->pluck('views');

        if ($subscribers < self::ENGAGEMENT_MIN_SUBSCRIBERS || $views->count() < self::ENGAGEMENT_MIN_POSTS) {
            return null;
        }

        return self::engagementCard($views->avg() / $subscribers * 100);
    }

    /** The engagement card for a views ÷ subscribers percentage (also used by the channel check) */
    public static function engagementCard(float $ratio): array
    {
        $level = $ratio >= 8 ? 'good' : ($ratio >= 3 ? 'warn' : 'bad');
        $shown = $ratio >= 10 ? (string) round($ratio) : rtrim(rtrim(number_format($ratio, 1), '0'), '.');

        return [
            'key' => 'engagement',
            'level' => $level,
            'value' => $shown.'%',
            'label' => __('نسبة التفاعل'),
            'status' => ['good' => __('تفاعل طبيعي'), 'warn' => __('تفاعل منخفض'), 'bad' => __('تفاعل منخفض جداً مقارنة بعدد المشتركين')][$level],
            'note' => __('من كل 100 مشترك يشاهد المنشورَ :n بالمتوسط. النسبة المنخفضة جداً قد تعني أن جزءاً من المشتركين غير حقيقي أو غير نشط.', ['n' => $shown]),
        ];
    }

    /** Posts we saw that were later deleted, over the last 30 days */
    protected static function deleted(Provider $provider): array
    {
        $watchingDays = (int) $provider->telegram_posts_since->diffInDays(now());

        if ($watchingDays < self::DELETED_AFTER_DAYS) {
            return [
                'key' => 'deleted',
                'level' => 'pending',
                'value' => '…',
                'label' => __('المنشورات المحذوفة'),
                'status' => __('قيد الرصد'),
                'note' => __('بدأنا متابعة منشورات القناة، وتظهر النتيجة بعد :n يوماً (:date).', [
                    'n' => self::DELETED_AFTER_DAYS - $watchingDays,
                    'date' => self::deletedReadyAt($provider)->translatedFormat('j F'),
                ]),
            ];
        }

        $count = $provider->telegramPosts()->where('deleted_at', '>=', now()->subDays(self::WINDOW_DAYS))->count();
        $level = $count <= 2 ? 'good' : ($count <= 10 ? 'warn' : 'bad');

        return [
            'key' => 'deleted',
            'level' => $level,
            'value' => number_format($count),
            'label' => __('منشور محذوف (30 يوماً)'),
            'status' => ['good' => __('لا يوجد حذف يُذكر'), 'warn' => __('حذفت بعض المنشورات'), 'bad' => __('حذفت عدداً كبيراً من المنشورات')][$level],
            'note' => __('رصدنا :n منشوراً نُشر ثم حُذف خلال آخر 30 يوماً. حذف المنشورات أمر عادي أحياناً، لكن الحذف المتكرر قد يُخفي صفقات خاسرة.', ['n' => number_format($count)]),
        ];
    }

    /** When it last posted, and how often over the last 30 days */
    protected static function activity(Provider $provider, Collection $posts): ?array
    {
        // The last read of the page knows it even when the posts themselves are past their 45 days
        $last = $provider->telegram_data['last_post_at'] ?? $provider->telegramPosts()->max('posted_at');
        if (! $last) {
            return null;
        }

        // A weekly rate over the days we've actually followed it (a week at least, a month at most)
        $watched = (int) $provider->telegram_posts_since->diffInDays(now());
        $span = min(self::WINDOW_DAYS, $watched);
        $perWeek = $watched >= 7
            ? (int) round($posts->where('posted_at', '>=', now()->subDays($span))->count() / $span * 7)
            : null;

        return self::activityCard(Carbon::parse($last), $perWeek);
    }

    /** The activity card: when it last posted, and how often a week when that's known (also used by the channel check) */
    public static function activityCard(Carbon $last, ?int $perWeek): array
    {
        $last = $last->copy()->setTimezone(config('app.timezone'));
        $days = (int) $last->diffInDays(now());
        $level = $days <= 3 ? 'good' : ($days <= 14 ? 'warn' : 'bad');

        return [
            'key' => 'activity',
            'level' => $level,
            'value' => $last->diffForHumans(),
            'label' => __('آخر منشور'),
            'status' => ['good' => __('نشطة'), 'warn' => __('نشاط متقطع'), 'bad' => __('شبه متوقفة')][$level],
            'note' => $perWeek !== null
                ? __('تنشر بمعدل :n منشورات أسبوعياً خلال آخر شهر.', ['n' => $perWeek])
                : __('آخر منشور على القناة كان :date.', ['date' => $last->translatedFormat('j F Y')]),
        ];
    }
}
