<?php

namespace App\Support;

use App\Models\Provider;
use App\Services\TelegramChannel;
use Illuminate\Support\Carbon;

/**
 * "Check a channel": a report on any public Telegram channel from one read of its public pages
 * (App\Services\TelegramChannel), plus what we know when it's already on the platform.
 * What needs following over time (deletions, subscriber history) is offered as monitoring instead.
 * Every card describes a number and never accuses (same wording as App\Support\TelegramInsights).
 */
class ChannelReport
{
    /** The order indicators are listed in wherever they're laid out in full (bot, comparison) */
    public const ORDER = ['engagement', 'reactions', 'verifiability', 'losses', 'age', 'comments', 'promotion', 'activity', 'edited', 'steady_views', 'deleted'];

    /** Indicators that only make sense for a trading channel (dropped when it isn't one) */
    public const TRADING_ONLY = ['verifiability', 'losses', 'promotion', 'edited'];

    /** Views closer together than this (standard deviation ÷ mean) are unusual for real audiences */
    public const STEADY_VIEWS_BELOW = 0.05;

    /**
     * @return array{status: string, url?: string, message?: string, channel?: array, cards?: array<string, array>,
     *               provider?: ?Provider, impersonators?: \Illuminate\Support\Collection, pending?: bool, preview?: bool,
     *               trading?: string}  trading: TradingChannel::TRADING | NOT_TRADING | UNSURE
     *         status: invalid | private | not_found | not_channel | unreachable | impersonator | report
     */
    public static function for(string $input): array
    {
        $url = SubmissionLinks::telegram($input);
        if (! $url || ! SubmissionLinks::isTelegram($url)) {
            return ['status' => TelegramChannel::INVALID, 'message' => self::message(TelegramChannel::INVALID)];
        }

        // A known scam account: that's the whole answer
        $check = AccountCheck::run($url);
        if ($check['status'] === 'impersonator') {
            return ['status' => 'impersonator', 'url' => $url, 'impersonators' => $check['impersonators']];
        }

        $channel = TelegramChannel::read($url);
        if ($channel['status'] !== TelegramChannel::CHANNEL) {
            return ['status' => $channel['status'], 'url' => $url, 'message' => self::message($channel['status'])];
        }

        // The lookup loads only a few columns; the report needs the whole record (rating, tracking)
        $provider = SubmissionLinks::existingProvider($channel['url'])?->fresh();
        $listed = $provider?->is_active && (! Launch::appliesToVisitor() || Launch::earlyOpen($provider));
        $stats = $channel['stats'] ?? [];
        $items = $stats['items'] ?? [];

        // Comments come from the Bot API, so they show even when the post preview is off
        $cards = self::cards($items, $channel['subscribers'], $stats['first_post_at'] ?? null)
            + array_filter(['comments' => TelegramInsights::commentsCard($channel['comments'] ?? null)]);

        // A channel already on the platform is a trading one by definition
        $trading = $listed ? TradingChannel::TRADING : TradingChannel::verdict($channel['title'], $channel['description'] ?? null, $items,
            collect($items)->map(fn ($p) => PostKind::of($p['text'] ?? '', $p['photo'] ?? false))->countBy()->all());
        if ($trading === TradingChannel::NOT_TRADING) {
            // Trades, claims, losses and sales mean nothing here; the audience indicators still do
            $cards = array_diff_key($cards, array_flip(self::TRADING_ONLY));
        }

        return [
            'status' => 'report',
            'url' => $channel['url'],
            'channel' => $channel,
            'preview' => (bool) ($stats['preview_enabled'] ?? false),
            'trading' => $trading,
            'cards' => $cards,
            'provider' => $listed ? $provider : null,
            // Our own indicators, when we've been following it (deletions and the rest)
            'followed' => $listed ? TelegramInsights::for($provider) : [],
            'pending' => ! $provider && SubmissionLinks::pendingSubmission($channel['url']),
        ];
    }

    /**
     * What one read of the posts shows: engagement, activity, edits, and unusually even views.
     *
     * @param  list<array{msg_id: int, posted_at: ?string, views: ?int, edited: bool}>  $items
     * @return array<string, array>
     */
    public static function cards(array $items, ?int $subscribers, ?string $firstPostAt = null): array
    {
        $posts = collect($items)->filter(fn ($p) => $p['posted_at'])
            ->map(fn ($p) => $p + ['at' => Carbon::parse($p['posted_at'])]);
        if ($posts->isEmpty()) {
            return [];
        }

        $cards = [];

        // Views of posts at least a day old (fresh ones are still gathering them)
        $views = $posts->filter(fn ($p) => $p['views'] !== null && $p['at'] <= now()->subDay())->pluck('views');
        if ($subscribers >= TelegramInsights::ENGAGEMENT_MIN_SUBSCRIBERS && $views->count() >= TelegramInsights::ENGAGEMENT_MIN_POSTS) {
            $cards['engagement'] = TelegramInsights::engagementCard($views->avg() / $subscribers * 100);
        }
        $reactions = TelegramInsights::reactionsCard($posts->filter(fn ($p) => $p['at'] <= now()->subDay())
            ->map(fn ($p) => ['views' => $p['views'], 'reactions' => $p['reactions'] ?? null])->values()->all());
        if ($reactions) {
            $cards['reactions'] = $reactions;
        }

        // What the posts are: trades given beforehand, results announced afterwards, things for sale
        $kinds = collect($items)->map(fn ($p) => PostKind::of($p['text'] ?? '', $p['photo'] ?? false))->countBy();
        $verifiability = TelegramInsights::verifiabilityCard($kinds[PostKind::SIGNAL] ?? 0, $kinds[PostKind::CLAIM] ?? 0, count($items));
        if ($verifiability) {
            $cards['verifiability'] = $verifiability;
        }
        if ($losses = TelegramInsights::lossesCard($kinds[PostKind::CLAIM] ?? 0, $kinds[PostKind::LOSS] ?? 0, count($items))) {
            $cards['losses'] = $losses;
        }

        // How often, over the stretch the latest posts cover
        $first = $posts->min('at');
        $days = max(1, $first->diffInDays(now()));
        $perWeek = $posts->count() >= 3 ? (int) round($posts->count() / $days * 7) : null;
        $cards['activity'] = TelegramInsights::activityCard($posts->max('at'), null);
        if ($perWeek !== null) {
            // "40 posts in the last 1 days, 280 posts a week" read badly: a single day is said as such, and
            // the weekly rate is a number with no noun to agree with (or "less than one" when it rounds to 0)
            $cards['activity']['note'] = ($days <= 1
                ? __('نشرت :count منشوراً خلال آخر يوم.', ['count' => $posts->count()])
                : __('نشرت :count منشوراً خلال آخر :days يوماً.', ['count' => $posts->count(), 'days' => (int) ceil($days)]))
                .' '.($perWeek >= 1
                    ? __('المعدل: :n في الأسبوع تقريباً.', ['n' => $perWeek])
                    : __('المعدل: أقل من منشور واحد في الأسبوع.'));
        }

        if ($firstPostAt) {
            $cards['age'] = TelegramInsights::ageCard(Carbon::parse($firstPostAt), $subscribers);
        }

        // Edits after posting (a signal moved after the trade did is a known trick; we can't see what changed)
        $edited = $posts->where('edited', true)->count();
        $share = $edited / $posts->count() * 100;
        $level = $share <= 10 ? 'good' : ($share <= 25 ? 'warn' : 'bad');
        $cards['edited'] = [
            'key' => 'edited',
            'level' => $level,
            'value' => __(':n من :total', ['n' => $edited, 'total' => $posts->count()]),
            'label' => __('منشورات معدّلة'),
            'status' => ['good' => __('تعديلات قليلة'), 'warn' => __('تعديلات متكررة'), 'bad' => __('تعديلات كثيرة')][$level],
            'note' => __('عُدّل :n من آخر :total منشوراً بعد نشرها. التعديل عادي أحياناً، لكن تعديل التوصيات بعد تحرك السعر أسلوب معروف للتلاعب. لا نعرف ما الذي تغيّر في كل منشور.',
                ['n' => $edited, 'total' => $posts->count()]),
        ];

        $brokers = collect($items)->flatMap(fn ($p) => PostKind::brokers($p['text'] ?? ''))->unique()->values()->all();
        if ($promotion = TelegramInsights::promotionCard($kinds[PostKind::AD] ?? 0, count($items), $brokers)) {
            $cards['promotion'] = $promotion;
        }

        // Real audiences vary from post to post; near-identical counts can mean bought views. Shown only when it stands out
        if ($views->count() >= TelegramInsights::ENGAGEMENT_MIN_POSTS && $views->avg() > 0) {
            $mean = $views->avg();
            $spread = sqrt($views->map(fn ($v) => ($v - $mean) ** 2)->avg()) / $mean;
            if ($spread < self::STEADY_VIEWS_BELOW) {
                $cards['steady_views'] = [
                    'key' => 'steady_views',
                    'level' => 'warn',
                    'value' => number_format($views->min()).'–'.number_format($views->max()),
                    'label' => __('مشاهدات متقاربة جداً'),
                    'status' => __('تقارب غير معتاد في المشاهدات'),
                    'note' => __('مشاهدات المنشورات متطابقة تقريباً، بينما تختلف عادةً من منشور لآخر. قد يعني هذا مشاهدات غير حقيقية، وقد يكون صدفة.'),
                ];
            }
        }

        return $cards;
    }

    public static function message(string $status): string
    {
        return \App\Http\Controllers\SubmissionController::message($status);
    }
}
