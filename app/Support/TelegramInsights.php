<?php

namespace App\Support;

use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Plain indicators from a channel's posts (App\Services\TelegramPosts), for its page: engagement
 * (views ÷ subscribers), trades given beforehand against results claimed afterwards, posts deleted
 * lately, how active it is, and how much of it sells something.
 * Each describes a number and never accuses; an indicator without enough data isn't shown at all.
 */
class TelegramInsights
{
    public const KEYS = ['engagement', 'reactions', 'verifiability', 'losses', 'deleted', 'activity', 'age', 'comments', 'promotion'];

    /** A channel this young with this many subscribers grew unusually fast */
    public const YOUNG_MONTHS = 6;

    public const YOUNG_BIG_SUBSCRIBERS = 20000;

    public const WINDOW_DAYS = 30;

    /** Deletions count only after this long following the channel (before it, "still watching") */
    public const DELETED_AFTER_DAYS = 14;

    public const ENGAGEMENT_MIN_SUBSCRIBERS = 500;

    public const ENGAGEMENT_MIN_POSTS = 10;

    /** Views per post above this share of the subscribers (in %) are flagged rather than called normal */
    public const ENGAGEMENT_ABOVE_SUBSCRIBERS = 100;

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
        $posts = $provider->telegramPosts()->where('posted_at', '>=', $since)->get(['msg_id', 'posted_at', 'views', 'reactions', 'kind', 'brokers', 'deleted_at']);
        $kinds = $posts->countBy('kind');
        $show = fn (string $key) => ! in_array($key, $hidden, true);

        return array_filter([
            'engagement' => $show('engagement') ? self::engagement($provider, $posts) : null,
            'reactions' => $show('reactions') ? self::reactionsCard($posts->filter(fn ($p) => ! $p->deleted_at && $p->posted_at <= now()->subDay())
                ->map(fn ($p) => ['views' => $p->views, 'reactions' => $p->reactions])->values()->all()) : null,
            // Deleted ones count too: a trade given and then removed was still given
            'verifiability' => $show('verifiability')
                ? self::verifiabilityCard($kinds[PostKind::SIGNAL] ?? 0, $kinds[PostKind::CLAIM] ?? 0, $posts->count()) : null,
            'losses' => $show('losses') ? self::lossesCard($kinds[PostKind::CLAIM] ?? 0, $kinds[PostKind::LOSS] ?? 0, $posts->count()) : null,
            'deleted' => $show('deleted') ? self::deleted($provider) : null,
            'activity' => $show('activity') ? self::activity($provider, $posts) : null,
            'age' => $show('age') && ($first = $provider->telegram_data['first_post_at'] ?? null)
                ? self::ageCard(Carbon::parse($first), $provider->telegram_members) : null,
            'comments' => $show('comments') ? self::commentsCard($provider->telegram_data['comments'] ?? null) : null,
            'promotion' => $show('promotion') ? self::promotionCard($kinds[PostKind::AD] ?? 0, $posts->count(),
                $posts->pluck('brokers')->filter()->flatMap(fn ($b) => explode(',', $b))->unique()->values()->all()) : null,
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
        $shown = $ratio >= 10 ? (string) round($ratio) : rtrim(rtrim(number_format($ratio, 1), '0'), '.');

        // More views than subscribers: forwards can do it, so can bought views. Unusual either way, not "normal"
        if ($ratio > self::ENGAGEMENT_ABOVE_SUBSCRIBERS) {
            return [
                'key' => 'engagement',
                'level' => 'warn',
                'value' => $shown.'%',
                'label' => __('نسبة التفاعل'),
                'status' => __('مشاهدات أكثر من عدد المشتركين'),
                'note' => __('متوسط مشاهدات المنشور أكبر من عدد مشتركي القناة. قد يحدث هذا عندما تنتشر منشوراتها خارجها بإعادة التوجيه، وقد يعني مشاهدات غير حقيقية.'),
            ];
        }

        $level = $ratio >= 8 ? 'good' : ($ratio >= 3 ? 'warn' : 'bad');

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

        $deleted = $provider->telegramPosts()->where('deleted_at', '>=', now()->subDays(self::WINDOW_DAYS))->pluck('kind');
        $count = $deleted->count();
        $trades = $deleted->filter(fn ($kind) => $kind === PostKind::SIGNAL)->count();
        $level = $count <= 2 ? 'good' : ($count <= 10 ? 'warn' : 'bad');

        return [
            'key' => 'deleted',
            'level' => $level,
            'value' => number_format($count),
            'label' => __('منشور محذوف (30 يوماً)'),
            'status' => ['good' => __('لا يوجد حذف يُذكر'), 'warn' => __('حذفت بعض المنشورات'), 'bad' => __('حذفت عدداً كبيراً من المنشورات')][$level],
            'note' => __('رصدنا :n منشوراً نُشر ثم حُذف خلال آخر 30 يوماً. حذف المنشورات أمر عادي أحياناً، لكن الحذف المتكرر قد يُخفي صفقات خاسرة.', ['n' => number_format($count)])
                // Deleted trades are the ones that matter most
                .($trades ? ' '.__('من بينها صفقات كانت منشورة بأرقام واضحة: :n.', ['n' => $trades]) : ''),
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

    /**
     * Trades given beforehand (checkable) against results announced afterwards (not checkable).
     * Null when there are too few of either to say anything.
     */
    public static function verifiabilityCard(int $signals, int $claims, int $total): ?array
    {
        if ($signals + $claims < 3) {
            return null;
        }

        $level = $signals === 0 ? 'bad' : ($signals < $claims ? 'warn' : 'good');

        return [
            'key' => 'verifiability',
            'level' => $level,
            'value' => __('صفقات :s · ادعاءات :c', ['s' => $signals, 'c' => $claims]),
            'label' => __('قابلية التحقق'),
            'status' => ['good' => __('تنشر صفقاتها مسبقاً'), 'warn' => __('ادعاءات أكثر من الصفقات'), 'bad' => __('تنشر نتائج دون صفقات مسبقة')][$level],
            'note' => __('في آخر :total منشوراً: صفقات منشورة قبل حدوثها بأرقام واضحة (يمكن التحقق منها): :s. إعلانات عن نتائج بعد حدوثها مثل «150 نقطة» (لا يمكن التحقق منها): :c. القناة التي تنشر النتائج فقط قد تختار الرابحة منها.',
                ['total' => $total, 's' => $signals, 'c' => $claims]),
        ];
    }

    /**
     * Emoji reactions per 1,000 views. Real audiences react; bought views don't. Null when there's too
     * little to go on, or when the posts show no reactions at all (the owner may have switched them off).
     *
     * @param  list<array{views: ?int, reactions: ?int}>  $posts  posts at least a day old
     */
    public static function reactionsCard(array $posts): ?array
    {
        $posts = array_filter($posts, fn ($p) => $p['views']);
        $reacted = array_filter($posts, fn ($p) => $p['reactions']);
        if (count($posts) < self::ENGAGEMENT_MIN_POSTS || count($reacted) < 3) {
            return null;
        }

        $views = array_sum(array_column($posts, 'views'));
        $reactions = array_sum(array_map(fn ($p) => $p['reactions'] ?? 0, $posts));
        $perThousand = $reactions / $views * 1000;
        $level = $perThousand >= 5 ? 'good' : ($perThousand >= 2 ? 'warn' : 'bad');
        $shown = $perThousand >= 10 ? (string) round($perThousand) : rtrim(rtrim(number_format($perThousand, 1), '0'), '.');

        return [
            'key' => 'reactions',
            'level' => $level,
            'value' => __(':n لكل 1000 مشاهدة', ['n' => $shown]),
            'label' => __('التفاعلات (الإيموجي)'),
            'status' => ['good' => __('تفاعل طبيعي مع المنشورات'), 'warn' => __('تفاعلات قليلة'), 'bad' => __('تفاعلات نادرة جداً مقارنة بالمشاهدات')][$level],
            'note' => __('في آخر :total منشوراً: :r تفاعلاً مقابل :v مشاهدة. في القنوات النشطة يتفاعل عادةً 5 إلى 20 من كل 1000 مشاهد، والتفاعلات القليلة جداً مقارنة بالمشاهدات قد تعني مشاهدات غير حقيقية.',
                ['total' => count($posts), 'r' => number_format($reactions), 'v' => number_format($views)]),
        ];
    }

    /** Whether subscribers can comment (a discussion group is linked). Null when Telegram didn't say. */
    public static function commentsCard(?bool $open): ?array
    {
        if ($open === null) {
            return null;
        }

        return [
            'key' => 'comments',
            'level' => $open ? 'good' : 'warn',
            'value' => $open ? __('مفتوحة') : __('مغلقة'),
            'label' => __('التعليقات'),
            'status' => $open ? __('يمكن التعليق على المنشورات') : __('لا يمكن التعليق على المنشورات'),
            'note' => $open
                ? __('يمكن للمشتركين التعليق على المنشورات، فتجاربهم وشكاواهم ظاهرة لمن يتابع القناة.')
                : __('القناة لا تسمح بالتعليق على منشوراتها، فلا تظهر تجارب المشتركين أو شكاواهم علناً. كثير من القنوات تغلقها، لكنها تستحق الانتباه مع المؤشرات الأخرى.'),
        ];
    }

    /** Results announced as wins against losses owned up to. No trader always wins. */
    public static function lossesCard(int $wins, int $losses, int $total): ?array
    {
        if ($wins + $losses < 3) {
            return null;
        }

        $level = $losses === 0 ? 'warn' : 'good';

        return [
            'key' => 'losses',
            'level' => $level,
            'value' => __('أرباح :w · خسائر :l', ['w' => $wins, 'l' => $losses]),
            'label' => __('الأرباح مقابل الخسائر'),
            'status' => $level === 'good' ? __('تذكر خساراتها أيضاً') : __('لا تذكر أي خسارة'),
            'note' => __('في آخر :total منشوراً: إعلانات عن أرباح: :w، وإعلانات عن خسائر مثل «ضرب الستوب»: :l. لا يوجد متداول يربح دائماً، والقناة التي لا تذكر خساراتها أبداً قد تكون تُخفيها.',
                ['total' => $total, 'w' => $wins, 'l' => $losses]),
        ];
    }

    /** How old the channel is, from its oldest post still there; fast growth for its age is pointed out */
    public static function ageCard(Carbon $first, ?int $subscribers): array
    {
        $months = (int) $first->diffInMonths(now());
        $fast = $months < self::YOUNG_MONTHS && $subscribers >= self::YOUNG_BIG_SUBSCRIBERS;
        $age = $first->copy()->locale(app()->getLocale())
            ->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);

        return [
            'key' => 'age',
            'level' => $fast ? 'warn' : 'good',
            'value' => $age,
            'label' => __('عمر القناة'),
            'status' => $fast ? __('قناة حديثة بعدد مشتركين كبير') : ($months >= 12 ? __('قناة قديمة نسبياً') : __('قناة حديثة')),
            'note' => __('أقدم منشور ما زال موجوداً على القناة نُشر في :date.', ['date' => $first->copy()->setTimezone(config('app.timezone'))->translatedFormat('j F Y')])
                .($fast ? ' '.__('وصول قناة بهذا العمر إلى :subs مشترك سريع، وقد يكون بسبب إعلانات ممولة أو مشتركين غير حقيقيين.', ['subs' => number_format($subscribers)]) : ''),
        ];
    }

    /**
     * Posts selling something: a private channel, a course, a broker sign-up.
     *
     * @param  list<string>  $brokers
     */
    public static function promotionCard(int $ads, int $total, array $brokers): ?array
    {
        if ($total < 10) {
            return null;
        }

        $share = $ads / $total * 100;
        $level = $share < 15 ? 'good' : ($share < 35 ? 'warn' : 'bad');

        return [
            'key' => 'promotion',
            'level' => $level,
            'value' => round($share).'%',
            'label' => __('منشورات ترويجية'),
            'status' => ['good' => __('ترويج قليل'), 'warn' => __('ترويج ملحوظ'), 'bad' => __('ترويج كثير')][$level],
            'note' => __(':n من آخر :total منشوراً تبيع شيئاً: اشتراكاً في قناة خاصة، أو دورة، أو تسجيلاً لدى وسيط.', ['n' => $ads, 'total' => $total])
                .($brokers ? ' '.__('تنشر روابط تسجيل لدى: :brokers، وقد تربح القناة من تسجيلك عبرها.', ['brokers' => implode('، ', $brokers)]) : ''),
        ];
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
