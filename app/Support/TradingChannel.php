<?php

namespace App\Support;

/**
 * Is a checked channel about trading at all? The channel check (App\Support\ChannelReport) is built for
 * signals and trading channels; on a news, sports or personal channel its trade-specific indicators
 * (trades vs claims, losses, promotion, edits) would read as nonsense, and it isn't one to monitor.
 *
 *   trading:      its name or description says so, or its posts carry trades or the words of the trade
 *   not_trading:  we read its posts and none of that is there
 *   unsure:       nothing to read beyond the name (post preview off) and the name doesn't say
 */
class TradingChannel
{
    public const TRADING = 'trading';

    public const NOT_TRADING = 'not_trading';

    public const UNSURE = 'unsure';

    /** Words only traders use (matched on SearchText::normalize'd text: ة→ه, أ→ا …) */
    protected const TRADING_WORDS = '/\b(forex|fx|xau|xauusd|trading|trader|traders|signals?|scalp(ing)?|us30|nas100|us100|mt4|mt5|metatrader|copy ?trading|smc|tp\d|stop ?loss|take ?profit)\b'
        // "150pips" is written stuck to its number
        .'|(?<![a-z])pips?\b'
        .'|(?<!\p{L})(ال)?(تداول|توصيات|توصيه|صفقات|صفقه|فوركس|سكالب|سكالبينج|ستوب|تحليل فني|لوت|بروكر|نسخ الصفقات|متداول|متداولين)(?!\p{L})'
        .'|\b[a-z]{3}(usd|eur|jpy|gbp|aud|cad|chf|nzd)\b'
        // Points counted the trader's way: "80نقطه", "150 نقاط"
        .'|\d+\s*(نقطه|نقاط|بيب)/u';

    /** Markets traders follow, but news and other channels mention too ("gold medal", "crypto wallet") */
    protected const MARKET_WORDS = '/\b(gold|crypto|bitcoin|btc|eth|usdt|binance|stocks?|nasdaq|futures)\b'
        .'|(?<!\p{L})(ال)?(ذهب|بورصه|اسهم|كريبتو|بيتكوين|عملات رقميه|عملات مشفره|شارت|سوق المال|فيوتشر)(?!\p{L})/u';

    /**
     * @param  list<array{text?: string}>  $items  the posts read from the preview
     * @param  array<string, int>  $kinds  post counts by App\Support\PostKind
     */
    public static function verdict(string $title, ?string $description, array $items, array $kinds = []): string
    {
        $about = SearchText::normalize($title.' '.$description);
        if (preg_match(self::TRADING_WORDS, $about) || preg_match(self::MARKET_WORDS, $about)) {
            return self::TRADING;
        }

        $texts = array_values(array_filter(array_map(fn ($p) => SearchText::normalize($p['text'] ?? ''), $items)));
        if (! $texts) {
            return self::UNSURE;
        }

        // Trades given and losses owned up to are specific; "✅ done" style claims aren't, so they don't count here
        $trades = ($kinds[PostKind::SIGNAL] ?? 0) + ($kinds[PostKind::LOSS] ?? 0);
        $tradingPosts = count(array_filter($texts, fn ($t) => preg_match(self::TRADING_WORDS, $t)));
        $marketPosts = count(array_filter($texts, fn ($t) => preg_match(self::MARKET_WORDS, $t)));

        return $trades >= 2 || $tradingPosts >= 2 || ($marketPosts >= 5 && $marketPosts * 2 >= count($texts))
            ? self::TRADING : self::NOT_TRADING;
    }
}
