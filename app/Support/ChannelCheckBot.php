<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

use function Illuminate\Support\defer;

/**
 * "Check a channel" through the bot: a channel link or @name sent to it gets the same report as
 * /check-channel (App\Support\ChannelReport), as the report's image with the indicators under it.
 * Reading the channel takes a few seconds, so the bot says it's on it and the report follows once
 * Telegram has had its answer (deferred until after the response).
 */
class ChannelCheckBot
{
    /** Checks per chat per 10 minutes */
    public const PER_TEN_MINUTES = 10;

    protected const DOTS = ['good' => '🟢', 'warn' => '🟡', 'bad' => '🔴', 'pending' => '⚪'];

    /** The order the indicators are listed in (the image shows the first four) */
    protected const ORDER = ['engagement', 'verifiability', 'losses', 'age', 'promotion', 'activity', 'edited', 'steady_views', 'deleted'];

    /** True when the message was a channel to check (then it's been answered) */
    public static function handles(int $chatId, string $text): bool
    {
        $input = self::channelIn($text);
        if (! $input) {
            return false;
        }

        if (! RateLimiter::attempt('bot-channel-check:'.$chatId, self::PER_TEN_MINUTES, fn () => true, 600)) {
            TelegramAppLogin::api('sendMessage', ['chat_id' => $chatId, 'text' => __('طلبات كثيرة خلال وقت قصير. حاول مرة أخرى بعد دقائق.')]);

            return true;
        }

        TelegramAppLogin::api('sendMessage', ['chat_id' => $chatId, 'text' => '⏳ '.__('جاري فحص القناة :channel…', ['channel' => $input])]);

        // After Telegram has its answer: reading the channel takes seconds, and a slow webhook gets the update sent again
        defer(function () use ($chatId, $input) {
            app()->setLocale('ar');
            rescue(fn () => self::report($chatId, $input), report: true);
        });

        return true;
    }

    public static function help(): string
    {
        return '🔎 '.__('افحص أي قناة توصيات قبل أن تشترك: أرسل لي رابطها أو معرّفها، مثل @channel أو t.me/channel، وسأرسل لك تقريراً مجانياً عنها.');
    }

    /** The channel named in a message: a t.me link, an @name, or the site's "check from Telegram" link (/start check_name) */
    public static function channelIn(string $text): ?string
    {
        $text = trim($text);

        return match (true) {
            (bool) preg_match('/^\/start\s+check_([A-Za-z][A-Za-z0-9_]{3,31})$/', $text, $m) => '@'.$m[1],
            (bool) preg_match('#(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog)/(?:s/)?[A-Za-z0-9_+]+#i', $text, $m) => $m[0],
            (bool) preg_match('/(?<![\w@])@([A-Za-z][A-Za-z0-9_]{3,31})\b/', $text, $m) => '@'.$m[1],
            default => null,
        };
    }

    /** Builds the report and sends it to the chat */
    public static function report(int $chatId, string $input): void
    {
        $report = ChannelReport::for($input);

        if ($report['status'] === 'impersonator') {
            $real = $report['impersonators']->first()?->provider;
            self::send($chatId, '⚠️ '.__('تحذير: هذا حساب منتحل معروف')."\n\n"
                .__('هذا الحساب ينتحل اسم «:name». لا ترسل له أموالاً ولا تشترك من خلاله.', ['name' => $real?->name]),
                $real ? [['⭐ '.__('القناة الأصلية على :app', ['app' => config('app.name')]), route('providers.show', $real)]] : []);

            return;
        }

        if ($report['status'] !== 'report') {
            self::send($chatId, '❕ '.$report['message']);

            return;
        }

        $channel = $report['channel'];
        $url = route('channel-check', ['channel' => '@'.$channel['username'], 'utm_source' => 'telegram', 'utm_medium' => 'bot']);
        $buttons = $report['provider']
            ? [['⭐ '.__('صفحة القناة والتقييمات'), route('providers.show', $report['provider'])], ['📄 '.__('التقرير الكامل'), $url]]
            : [['📄 '.__('التقرير الكامل وإضافتها للمراقبة'), $url]];

        self::send($chatId, self::caption($report), $buttons, route('share-images.check', ['ar', $channel['username']]));
    }

    /** The report as text, the indicators one per line (under Telegram's 1024-character caption limit) */
    public static function caption(array $report): string
    {
        $channel = $report['channel'];
        $cards = ($report['cards'] ?? []) + array_intersect_key($report['followed'] ?? [], ['deleted' => true]);

        $lines = ['📊 '.__('تقرير قناة :name', ['name' => $channel['title']])];
        $lines[] = '@'.$channel['username'].($channel['subscribers'] ? ' · '.trans_choice(':count مشترك', $channel['subscribers'], ['count' => number_format($channel['subscribers'])]) : '');
        $lines[] = '';

        foreach (self::ORDER as $key) {
            if ($card = $cards[$key] ?? null) {
                $lines[] = self::DOTS[$card['level']].' '.$card['label'].': '.$card['value'].' — '.$card['status'];
            }
        }

        if (! $cards) {
            $lines[] = __('صاحب القناة أغلق معاينة المنشورات للعامة، لذلك لا يمكن قياس التفاعل والنشاط. نعرض بيانات القناة الأساسية فقط.');
        }

        if ($report['provider'] ?? null) {
            $lines[] = '';
            $lines[] = '✅ '.__('القناة موجودة على :app', ['app' => config('app.name')]);
        }

        $lines[] = '';
        $lines[] = 'ℹ️ '.__('التقرير محسوب تلقائياً من بيانات القناة العامة على تيليجرام، وهو ليس نصيحة مالية.');

        return mb_substr(implode("\n", $lines), 0, 1020);
    }

    /**
     * The image with the text as its caption; then without the image, then without the buttons, when
     * Telegram refuses one (it can't fetch images from a local site, and refuses some addresses in buttons).
     *
     * @param  list<array{0: string, 1: string}>  $buttons  [label, url]
     */
    protected static function send(int $chatId, string $text, array $buttons = [], ?string $photo = null): void
    {
        $markup = $buttons ? ['reply_markup' => ['inline_keyboard' => array_map(fn ($b) => [['text' => $b[0], 'url' => $b[1]]], $buttons)]] : [];

        $attempts = array_filter([
            $photo ? ['sendPhoto', ['chat_id' => $chatId, 'photo' => $photo, 'caption' => $text] + $markup] : null,
            ['sendMessage', ['chat_id' => $chatId, 'text' => $text] + $markup],
            ['sendMessage', ['chat_id' => $chatId, 'text' => $text]],
        ]);

        foreach ($attempts as [$method, $params]) {
            try {
                TelegramAppLogin::api($method, $params);

                return;
            } catch (RuntimeException) {
                // Try the simpler form
            }
        }
    }
}
