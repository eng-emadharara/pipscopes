<?php

namespace App\Support;

/**
 * Sorts a channel post by what it is, from its text (rules only, Arabic and English):
 *   signal: a trade given before it plays out (direction + target/stop + prices), so it can be checked
 *   claim:  a result announced afterwards ("150 pips done", "اتحقق", screenshots of profits with a caption)
 *   ad:     selling something (broker sign-up links, VIP / private channel, courses, bonuses)
 *   other:  everything else (news, greetings, links to videos, photos without a caption)
 * A claim can't be checked; a signal can. That gap is what the channel report shows.
 */
class PostKind
{
    public const SIGNAL = 'signal';

    public const CLAIM = 'claim';

    public const AD = 'ad';

    public const OTHER = 'other';

    /** Brokers and exchanges whose sign-up links channels earn from (matched in links) */
    public const BROKERS = [
        'justmarkets' => 'JustMarkets', 'exness' => 'Exness', 'xmglobal' => 'XM', 'xm.com' => 'XM', 'icmarkets' => 'IC Markets',
        'infinox' => 'Infinox', 'headway' => 'Headway', 'hfm.com' => 'HFM', 'hotforex' => 'HFM', 'vantage' => 'Vantage',
        'fbs.com' => 'FBS', 'octafx' => 'Octa', 'octa.com' => 'Octa', 'roboforex' => 'RoboForex', 'tickmill' => 'Tickmill',
        'fxtm' => 'FXTM', 'axi.com' => 'Axi', 'pepperstone' => 'Pepperstone', 'avatrade' => 'AvaTrade', 'ingot' => 'Ingot',
        'equiti' => 'Equiti', 'capital.com' => 'Capital.com', 'plus500' => 'Plus500', 'puprime' => 'PU Prime', 'fpmarkets' => 'FP Markets',
        'binance' => 'Binance', 'bybit' => 'Bybit', 'mexc' => 'MEXC', 'okx' => 'OKX', 'bingx' => 'BingX', 'weex' => 'WEEX', 'bitget' => 'Bitget',
    ];

    public static function of(string $text, bool $photo = false): string
    {
        $t = SearchText::normalize($text);
        if ($t === '') {
            return self::OTHER;
        }

        return match (true) {
            self::isSignal($t) => self::SIGNAL,
            self::isAd($t) => self::AD,
            self::isClaim($t) => self::CLAIM,
            default => self::OTHER,
        };
    }

    /** A direction, a target or stop, and at least two prices */
    protected static function isSignal(string $t): bool
    {
        $direction = preg_match('/\b(buy|sell)\b|(?<!\p{L})(شراء|بيع|شري)(?!\p{L})/u', $t);
        $levels = preg_match('/\b(tp\d?|sl|take ?profit|stop ?loss|target|entry)\b|هدف|الهدف|اهداف|الاهداف|ستوب|وقف|دخول|الدخول/u', $t);
        $prices = preg_match_all('/(?<![\d.])\d{1,6}(?:[.,]\d{1,5})?(?![\d.])/u', $t);

        return $direction && $levels && $prices >= 2;
    }

    protected static function isAd(string $t): bool
    {
        if (self::brokers($t)) {
            return true;
        }

        // Affiliate-looking links (sign-up codes, partner ids)
        if (preg_match('#https?://\S*(/a/|[?&](ref|aff|affid|ib|partner|referral|invite)=|/links/go/|/register\?|/signup\?)#i', $t)) {
            return true;
        }

        return (bool) preg_match('/\b(vip|bonus|cashback|masterclass|course|discount|promo)\b|بروكر|وكال(ه|تي)|بونص|كاش باك|كورس|الدوره|دوره|دورتي|الخاصه|خاصتي|للاشتراك|الاشتراك|اشترك|عرض حصري|عرض خاص|خصم|رابط التسجيل|التسجيل|سجل معي|سجلوا|الرقم المرجعي/u', $t);
    }

    /** Pips or points won, a target "hit", "it came true", profits announced */
    protected static function isClaim(string $t): bool
    {
        // Market news: "25 نقطة أساس" (basis points), "جني أرباح" (profit-taking) aren't anyone's results
        $t = preg_replace('/\d*\s*نقطه اساس|جني (ال)?ارباح/u', '', $t);

        return (bool) preg_match(
            '/\d+\s*(pips?|pip|نقطه|نقاط|بيب)|\b(pips?|نقطه|نقاط)\s*\d+|(الف|ألف|مئه|ميه)\s*(نقطه|بيب)'
            .'|\b(done|tp\s*hit|hit|running|profit|profits)\b|✅|✔|تحقق|اتحقق|تحققت|ضرب(ت)? (ال)?هدف|وصل(ت)? (ل)?(ال)?(اهداف|هدف)'
            .'|(زيرو|صفر) انعكاس|ربحت|ربحنا|كسبت|ارباح|ارباحكم|مبروك/u',
            $t
        );
    }

    /**
     * Brokers whose links are in the text.
     *
     * @return list<string>
     */
    public static function brokers(string $text): array
    {
        if (! preg_match_all('#https?://[^\s]+#i', $text, $links)) {
            return [];
        }

        $found = [];
        foreach ($links[0] as $link) {
            $host = strtolower((string) parse_url($link, PHP_URL_HOST)).strtolower((string) parse_url($link, PHP_URL_PATH));
            foreach (self::BROKERS as $needle => $name) {
                if (str_contains($host, $needle)) {
                    $found[$name] = true;
                }
            }
        }

        return array_keys($found);
    }
}
