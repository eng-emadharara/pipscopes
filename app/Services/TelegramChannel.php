<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads a public Telegram channel from its public web pages (no account or API key):
 * t.me/<name> gives the title, description, photo, subscriber count and verified badge;
 * t.me/s/<name> (the post preview, when the owner hasn't turned it off) gives media
 * counters and the latest posts' dates and views.
 */
class TelegramChannel
{
    /** Outcomes of read(): only "channel" can be added to the platform */
    public const CHANNEL = 'channel';

    public const PRIVATE = 'private';         // invite link (t.me/+…, t.me/joinchat/…)

    public const NOT_CHANNEL = 'not_channel'; // a person, bot or group

    public const NOT_FOUND = 'not_found';

    public const UNREACHABLE = 'unreachable'; // Telegram didn't answer; try again later

    public const INVALID = 'invalid';

    /** The public username in a t.me link, or the outcome when there is none. */
    public static function username(?string $url): array
    {
        $host = Str::lower(preg_replace('/^www\./i', '', (string) parse_url((string) $url, PHP_URL_HOST)));
        if (! in_array($host, ['t.me', 'telegram.me', 'telegram.dog'], true)) {
            return ['status' => self::INVALID];
        }

        $segments = array_values(array_filter(explode('/', (string) parse_url((string) $url, PHP_URL_PATH))));
        $first = $segments[0] ?? '';
        if ($first === 's') {
            $first = $segments[1] ?? '';
        }

        if (Str::startsWith($first, '+') || $first === 'joinchat') {
            return ['status' => self::PRIVATE];
        }

        return preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $first)
            ? ['status' => 'ok', 'username' => $first]
            : ['status' => self::INVALID];
    }

    /**
     * @return array{status: string, username?: string, url?: string, title?: string, description?: ?string,
     *               photo_url?: ?string, subscribers?: ?int, verified?: bool, stats?: array<string, mixed>}
     */
    public static function read(?string $url, bool $fresh = false): array
    {
        $parsed = self::username($url);
        if ($parsed['status'] !== 'ok') {
            return $parsed;
        }

        $username = $parsed['username'];
        $key = 'tg-channel:'.Str::lower($username);

        // The form checks the link while typing and again on save: one fetch serves both.
        // The daily refresh always asks Telegram ($fresh).
        if (! $fresh && ($cached = Cache::get($key))) {
            return $cached;
        }

        $result = self::fetch($username);
        if ($result['status'] !== self::UNREACHABLE) {
            Cache::put($key, $result, now()->addMinutes(15));
        }

        return $result;
    }

    protected static function fetch(string $username): array
    {
        try {
            $html = Http::timeout(8)->withHeaders(['Accept-Language' => 'en'])->get('https://t.me/'.$username)->body();
        } catch (\Throwable) {
            return ['status' => self::UNREACHABLE];
        }

        $title = self::meta($html, 'og:title');
        // Unknown usernames get a generic "Telegram: Contact @name" page
        if (! $title || Str::startsWith($title, 'Telegram: ') || ! str_contains($html, 'tgme_page_title')) {
            return ['status' => self::NOT_FOUND, 'username' => $username];
        }

        $extra = preg_match('/class="tgme_page_extra">\s*([^<]+)</', $html, $m) ? trim(html_entity_decode($m[1])) : '';
        // Channels show "N subscribers"; groups "N members"; people and bots neither
        if (! str_contains($extra, 'subscriber')) {
            return ['status' => self::NOT_CHANNEL, 'username' => $username];
        }

        $description = self::meta($html, 'og:description');
        $photo = self::meta($html, 'og:image');

        return [
            'status' => self::CHANNEL,
            'username' => $username,
            'url' => 'https://t.me/'.$username,
            'title' => Str::limit($title, 150, ''),
            // Channels without a description get Telegram's generic text
            'description' => $description && ! Str::startsWith($description, ['You can view and join', 'If you have Telegram']) ? $description : null,
            // Channels without a photo get Telegram's logo
            'photo_url' => $photo && ! str_contains($photo, 'telegram.org/img/') ? $photo : null,
            'subscribers' => self::number($extra),
            'verified' => str_contains($html, 'verified-icon'),
            'comments' => self::commentsOpen($username),
            'stats' => self::posts($username),
        ];
    }

    /** Counters and recent activity from the post preview page; empty when the owner turned it off. */
    protected static function posts(string $username): array
    {
        try {
            $response = Http::timeout(8)->withHeaders(['Accept-Language' => 'en'])->get('https://t.me/s/'.$username);
        } catch (\Throwable) {
            return [];
        }

        $html = $response->body();
        if (! str_contains($html, 'tgme_widget_message')) {
            return ['preview_enabled' => false, 'fetched_at' => now()->toIso8601String()];
        }

        $counters = [];
        preg_match_all('/counter_value">([^<]+)<\/span>\s*<span class="counter_type">([^<]+)</', $html, $m, PREG_SET_ORDER);
        foreach ($m as [, $value, $type]) {
            $counters[rtrim(trim($type), 's')] = self::number($value);
        }

        preg_match_all('/tgme_widget_message_views">([^<]+)</', $html, $views);
        preg_match_all('/<time datetime="([^"]+)"/', $html, $dates);
        $viewCounts = array_filter(array_map(fn ($v) => self::number($v), $views[1]));

        // Each post on the page, plus the page before it, so a deletion among the last ~40 posts shows
        $items = self::postItems($html, $username);
        if ($items) {
            try {
                $before = Http::timeout(8)->withHeaders(['Accept-Language' => 'en'])
                    ->get('https://t.me/s/'.$username, ['before' => min(array_column($items, 'msg_id'))])->body();
                $items = [...self::postItems($before, $username), ...$items];
            } catch (\Throwable) {
                // The first page alone still counts
            }
        }

        return [
            'items' => $items,
            // The oldest post still there: how old the channel is (read once a month, it doesn't move)
            'first_post_at' => $items ? self::firstPostAt($username) : null,
            'preview_enabled' => true,
            'photos' => $counters['photo'] ?? null,
            'videos' => $counters['video'] ?? null,
            'links' => $counters['link'] ?? null,
            'files' => $counters['file'] ?? null,
            'recent_posts' => count($dates[1]),
            'last_post_at' => $dates[1] ? max($dates[1]) : null,
            'avg_views' => $viewCounts ? (int) round(array_sum($viewCounts) / count($viewCounts)) : null,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The posts on a preview page, by number: when, how many views, whether it was edited.
     * Telegram numbers a channel's posts in order, so a number that's gone was deleted.
     *
     * The text (with each link's address after its label) is only used to sort the post (App\Support\PostKind); it isn't stored.
     *
     * @return list<array{msg_id: int, posted_at: ?string, views: ?int, edited: bool, text: string, photo: bool}>
     */
    public static function postItems(string $html, string $username): array
    {
        $parts = preg_split('/(?=data-post="'.preg_quote($username, '/').'\/\d+")/i', $html);
        $items = [];

        foreach ($parts as $part) {
            if (! preg_match('/^data-post="[^\/"]+\/(\d+)"/', $part, $id)) {
                continue;
            }

            $items[(int) $id[1]] = [
                'msg_id' => (int) $id[1],
                'posted_at' => preg_match('/tgme_widget_message_date"[^>]*><time datetime="([^"]+)"/', $part, $m) ? $m[1] : null,
                'views' => preg_match('/tgme_widget_message_views">([^<]+)</', $part, $m) ? self::number($m[1]) : null,
                // The meta line reads "edited" right before the post's date link
                'edited' => (bool) preg_match('/edited\s*(?:&nbsp;)?\s*<a class="tgme_widget_message_date"/', $part),
                'text' => preg_match('/<div class="tgme_widget_message_text[^"]*"[^>]*>(.*?)<\/div>/s', $part, $m) ? self::plainText($m[1]) : '',
                'photo' => str_contains($part, 'tgme_widget_message_photo_wrap'),
                // Emoji reactions added up; null when the post shows none (reactions can be switched off)
                'reactions' => preg_match_all('/<span class="tgme_reaction[^"]*">.*?<\/i>([\d.,]+\s*[KM]?)<\/span>/s', $part, $r)
                    ? array_sum(array_map(fn ($n) => self::number($n) ?? 0, $r[1])) : null,
            ];
        }

        return array_values($items);
    }

    /**
     * Whether the channel has a discussion group (comments under its posts), from the Bot API's getChat,
     * which public pages don't show. Null without a bot token or when Telegram doesn't answer. Cached a day.
     */
    public static function commentsOpen(string $username): ?bool
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            return null;
        }

        $key = 'tg-comments:'.Str::lower($username);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $response = Http::timeout(8)->get("https://api.telegram.org/bot$token/getChat", ['chat_id' => '@'.$username]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->json('ok')) {
            return null;
        }

        $open = filled($response->json('result.linked_chat_id'));
        Cache::put($key, $open, now()->addDay());

        return $open;
    }

    /** Date of the oldest post still on the channel (the preview's first page after post 1) */
    public static function firstPostAt(string $username): ?string
    {
        return Cache::remember('tg-first-post:'.Str::lower($username), now()->addDays(30), function () use ($username) {
            try {
                $html = Http::timeout(8)->withHeaders(['Accept-Language' => 'en'])->get('https://t.me/s/'.$username, ['after' => 1])->body();
            } catch (\Throwable) {
                return null;
            }

            $dates = array_filter(array_column(self::postItems($html, $username), 'posted_at'));

            return $dates ? min($dates) : null;
        });
    }

    /** A post's HTML as one line of text, each link followed by its address (so broker links show) */
    protected static function plainText(string $html): string
    {
        $html = preg_replace('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', '$2 $1 ', $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        return trim(preg_replace('/[ \t]+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * Saves the channel photo on our disk, so it keeps working after Telegram's link expires.
     * Returns the stored path, or null (the caller then keeps Telegram's URL).
     */
    public static function storePhoto(?string $photoUrl, string $username): ?string
    {
        if (! $photoUrl) {
            return null;
        }

        try {
            $body = Http::timeout(8)->get($photoUrl)->body();
        } catch (\Throwable) {
            return null;
        }

        $info = $body ? @getimagesizefromstring($body) : false;
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (! $extension || strlen($body) > 5 * 1024 * 1024) {
            return null;
        }

        $path = 'avatars/tg-'.Str::lower($username).'-'.substr(sha1($body), 0, 8).'.'.$extension;
        Storage::disk('public')->put($path, $body);

        return $path;
    }

    protected static function meta(string $html, string $property): ?string
    {
        return preg_match('/<meta property="'.preg_quote($property, '/').'" content="([^"]*)"/', $html, $m)
            ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)) ?: null
            : null;
    }

    /** "9 235 801 subscribers", "9.24M", "1.2K" → integer */
    protected static function number(string $text): ?int
    {
        $text = trim($text);
        if (preg_match('/^([\d.]+)\s*([KM])/i', $text, $m)) {
            return (int) round((float) $m[1] * (Str::upper($m[2]) === 'M' ? 1_000_000 : 1_000));
        }

        $digits = preg_replace('/\D/', '', Str::before($text, ' s'));

        return $digits !== '' ? (int) $digits : null;
    }
}
