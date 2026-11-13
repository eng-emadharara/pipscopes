<?php

namespace App\Support;

use App\Jobs\SendWatchAlert;
use App\Models\ChannelWatch;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;

/**
 * "Follow this channel" through the bot: a chat follows a channel on the platform (from the site's
 * t.me/<bot>?start=watch_<id> link or the button under a bot report) and is told when something
 * happens to it: posts deleted, a new name, a sudden jump in subscribers, a new review, an impersonator found.
 */
class ChannelWatching
{
    /** A jump this big in one read (and at least this many people) is worth an alert */
    public const JUMP_SHARE = 0.2;

    public const JUMP_MIN = 2000;

    public static function startLink(Provider $provider): ?string
    {
        return TelegramAppLogin::isOn() ? 'https://t.me/'.TelegramAppLogin::botUsername().'?start=watch_'.$provider->id : null;
    }

    /** "/start watch_<id>": follow. True when the message was one. */
    public static function handlesStart(int $chatId, string $text): bool
    {
        if (! preg_match('/^\/start\s+watch_(\d{1,10})$/', trim($text), $m)) {
            return false;
        }

        self::follow($chatId, (int) $m[1]);

        return true;
    }

    /** "/channels" (or "قنواتي"): the channels this chat follows, each with its stop button. True when the message was one. */
    public static function handlesList(int $chatId, string $text): bool
    {
        if (! preg_match('/^(\/channels(@\w+)?|قنواتي)$/u', trim($text))) {
            return false;
        }

        // Newest follow first
        $watches = ChannelWatch::where('chat_id', $chatId)->with('provider')->orderByDesc('id')->get()->filter(fn ($w) => $w->provider)->values();

        if ($watches->isEmpty()) {
            TelegramAppLogin::api('sendMessage', ['chat_id' => $chatId, 'text' => '🔕 '.__('لا تتابع أي قناة بعد.')."\n\n"
                .__('لمتابعة قناة: افتح صفحتها على الموقع واضغط «تابع القناة»، أو أرسل لي رابطها لأفحصها ثم اضغط «تابع».')]);

            return true;
        }

        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            // Arabic counts its nouns by number: one, two, three to ten, more
            'text' => '🔔 '.match (true) {
                $watches->count() === 1 => __('تتابع قناة واحدة:'),
                $watches->count() === 2 => __('تتابع قناتين:'),
                $watches->count() <= 10 => __('تتابع :count قنوات:', ['count' => $watches->count()]),
                default => __('تتابع :count قناة:', ['count' => $watches->count()]),
            }."\n\n"
                .$watches->map(fn ($w) => '• '.$w->provider->name)->join("\n")
                ."\n\n".__('اضغط على اسم القناة لفتح صفحتها، أو على «إيقاف» لإيقاف تنبيهاتها.'),
            // One row per channel: its page, and stop (a channel's own name could be long, so stop stays short)
            'reply_markup' => ['inline_keyboard' => $watches->take(50)->map(fn ($w) => [
                ['text' => mb_strimwidth($w->provider->name, 0, 40, '…'), 'url' => route('providers.show', $w->provider)],
                ['text' => '🔕 '.__('إيقاف'), 'callback_data' => 'w:off:'.$w->provider_id],
            ])->values()->all()],
        ]);

        return true;
    }

    /** The follow / stop buttons (w:on:<id>, w:off:<id>). True when the button was one. */
    public static function handlesCallback(array $callback): bool
    {
        if (! preg_match('/^w:(on|off):(\d{1,10})$/', (string) ($callback['data'] ?? ''), $m)) {
            return false;
        }

        $chatId = (int) data_get($callback, 'message.chat.id');
        TelegramAppLogin::api('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        $m[1] === 'on' ? self::follow($chatId, (int) $m[2]) : self::unfollow($chatId, (int) $m[2]);

        return true;
    }

    public static function follow(int $chatId, int $providerId): void
    {
        $provider = Provider::active()->find($providerId);
        if (! $provider) {
            TelegramAppLogin::api('sendMessage', ['chat_id' => $chatId, 'text' => __('لم نجد هذه القناة على المنصة.')]);

            return;
        }

        ChannelWatch::firstOrCreate(['chat_id' => $chatId, 'provider_id' => $provider->id]);

        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => '🔔 '.__('ستصلك هنا تنبيهات قناة «:name»:', ['name' => $provider->name])."\n"
                .'• '.__('إذا حذفت منشورات أو صفقات')."\n"
                .'• '.__('إذا غيّرت اسمها')."\n"
                .'• '.__('إذا قفز عدد مشتركيها فجأة')."\n"
                .'• '.__('عند نشر تقييم جديد عنها')."\n"
                .'• '.__('إذا اكتُشف حساب ينتحل اسمها')
                ."\n\n".__('لرؤية كل القنوات التي تتابعها أرسل /channels'),
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '🔕 '.__('إيقاف التنبيهات'), 'callback_data' => 'w:off:'.$provider->id]],
            ]],
        ]);
    }

    public static function unfollow(int $chatId, int $providerId): void
    {
        ChannelWatch::where('chat_id', $chatId)->where('provider_id', $providerId)->delete();
        $name = Provider::find($providerId)?->name;

        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => '🔕 '.__('أوقفنا تنبيهات قناة «:name».', ['name' => $name]),
            'reply_markup' => ['inline_keyboard' => [[['text' => '🔔 '.__('تابعها من جديد'), 'callback_data' => 'w:on:'.$providerId]]]],
        ]);
    }

    /** Sends to the channel's followers (from the queue); nothing when nobody follows it */
    public static function alert(Provider $provider, string $text): void
    {
        if (ChannelWatch::where('provider_id', $provider->id)->exists()) {
            SendWatchAlert::dispatch($provider->id, $text);
        }
    }

    // ---- What's worth an alert (called where it happens) ------------------------------------

    public static function deleted(Provider $provider, int $posts, int $trades): void
    {
        if ($posts < 1) {
            return;
        }

        $words = ['name' => $provider->name, 'n' => $posts];
        self::alert($provider, '🗑 '.match (true) {
            $posts === 1 => __('قناة «:name» حذفت منشوراً واحداً.', $words),
            $posts === 2 => __('قناة «:name» حذفت منشورين.', $words),
            $posts <= 10 => __('قناة «:name» حذفت :n منشورات.', $words),
            default => __('قناة «:name» حذفت :n منشوراً.', $words),
        }
            .($trades ? ' '.__('من بينها صفقات كانت منشورة بأرقام واضحة: :n.', ['n' => $trades]) : ''));
    }

    public static function renamed(Provider $provider, string $from, string $to): void
    {
        if ($from === '' || $from === $to) {
            return;
        }

        self::alert($provider, '✏️ '.__('قناة «:from» غيّرت اسمها على تيليجرام إلى «:to».', ['from' => $from, 'to' => $to]));
    }

    public static function subscribers(Provider $provider, ?int $before, ?int $after): void
    {
        if (! $before || ! $after || $after - $before < self::JUMP_MIN || ($after - $before) / $before < self::JUMP_SHARE) {
            return;
        }

        self::alert($provider, '📈 '.__('قفزة مفاجئة في مشتركي قناة «:name»: من :before إلى :after.', [
            'name' => $provider->name, 'before' => number_format($before), 'after' => number_format($after),
        ]).' '.__('النمو السريع جداً قد يكون بسبب إعلانات ممولة أو مشتركين غير حقيقيين.'));
    }

    public static function review(Review $review): void
    {
        if ($review->status !== Review::STATUS_APPROVED) {
            return;
        }

        self::alert($review->provider, '⭐ '.__('تقييم جديد لقناة «:name»: :stars', [
            'name' => $review->provider->name, 'stars' => str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating),
        ])."\n\n«".$review->headline(200).'»');
    }

    public static function impersonator(Impersonator $impersonator): void
    {
        self::alert($impersonator->provider, '⚠️ '.__('اكتشفنا حساباً ينتحل اسم قناة «:name»: :handle. لا تتعامل معه.', [
            'name' => $impersonator->provider->name, 'handle' => $impersonator->handle,
        ]));
    }
}
