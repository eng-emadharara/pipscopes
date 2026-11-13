<?php

namespace App\Support;

/**
 * The bot's buttons: a keyboard that stays under the message box in the private chat (check a channel,
 * I was scammed, my channels, free tools), what each one answers, and the bot's description shown
 * before Start. Pressing a button sends its label as a message, so the labels are matched here.
 */
class BotMenu
{
    public const CHECK = 'check';

    public const SCAM = 'scam';

    public const CHANNELS = 'channels';

    public const TOOLS = 'tools';

    /** @return array<string, string> key => label (the bot always speaks Arabic, so the labels are fixed) */
    public static function labels(): array
    {
        return [
            self::CHECK => '🔎 افحص قناة',
            self::SCAM => '🛟 نُصب عليّ',
            self::CHANNELS => '🔔 قنواتي',
            self::TOOLS => '🧰 أدوات مجانية',
        ];
    }

    /** The keyboard under the message box, kept until the chat replaces it */
    public static function keyboard(): array
    {
        $l = self::labels();

        return [
            'keyboard' => [
                [['text' => $l[self::CHECK]], ['text' => $l[self::SCAM]]],
                [['text' => $l[self::CHANNELS]], ['text' => $l[self::TOOLS]]],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
            'input_field_placeholder' => 'أرسل رابط قناة لفحصها…',
        ];
    }

    /** A button of the keyboard was pressed: answered. True when the message was one. */
    public static function handles(int $chatId, string $text): bool
    {
        $key = array_search(trim($text), self::labels(), true);
        if ($key === false) {
            return false;
        }

        match ($key) {
            self::CHECK => TelegramAppLogin::api('sendMessage', [
                'chat_id' => $chatId,
                'text' => '🔎 '.__('أرسل لي رابط القناة أو معرّفها، مثل @channel أو t.me/channel، ويصلك تقرير مجاني خلال ثوانٍ.'),
                'reply_markup' => ['inline_keyboard' => [[['text' => '🌐 '.__('افحص من الموقع'), 'url' => self::url('channel-check')]]]],
            ]),
            self::SCAM => self::scam($chatId),
            self::CHANNELS => ChannelWatching::handlesList($chatId, '/channels'),
            self::TOOLS => TelegramAppLogin::api('sendMessage', [
                'chat_id' => $chatId,
                'text' => '🧰 '.__('أدوات مجانية للمتداول على :site:', ['site' => config('app.name')]),
                'reply_markup' => ['inline_keyboard' => [
                    [['text' => '🛡 '.__('حاسبة إدارة رأس المال'), 'url' => self::url('tools.money-management')]],
                    [['text' => '📏 '.__('حاسبة حجم اللوت'), 'url' => self::url('tools.lot-size')], ['text' => '💲 '.__('قيمة النقطة'), 'url' => self::url('tools.pip-value')]],
                    [['text' => '⚖️ '.__('قارن بين قناتين'), 'url' => self::url('channel-compare')], ['text' => '🕵️ '.__('هل هذا الحساب حقيقي؟'), 'url' => self::url('account-check')]],
                    [['text' => '🧰 '.__('كل الأدوات'), 'url' => self::url('tools.index')]],
                ]],
            ]),
        };

        return true;
    }

    /** "/scam" or the button: what to do first, and where to go on the site */
    public static function scam(int $chatId): void
    {
        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => '🛟 '.__('نُصب عليك من قناة توصيات أو حساب على تيليجرام؟')."\n\n"
                .__('أوقف أي تحويل جديد، واحفظ صور المحادثات والإيصالات الآن. ثم افتح الصفحة لتعرف خطواتك حسب ما حدث وطريقة دفعك، وبلّغ حتى نجمع بلاغك مع بلاغات الآخرين عن نفس الجهة.')
                ."\n\n".__('لا نطلب منك أي مبلغ، وأي شخص يعدك باسترجاع أموالك مقابل رسوم فهو نصاب.'),
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '🛟 '.__('ماذا أفعل الآن؟'), 'url' => self::url('scam-help')]],
                [['text' => '🚨 '.__('قدّم بلاغاً'), 'url' => self::url('scam-reports.create')]],
            ]],
        ]);
    }

    /** Start: the welcome with the site's main pages under it, then the keyboard (one message can't carry both) */
    public static function welcome(int $chatId): void
    {
        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => ChannelCheckBot::welcome(),
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '🔎 '.__('افحص قناة من الموقع'), 'url' => self::url('channel-check')]],
                [['text' => '🛟 '.__('نُصب عليك؟'), 'url' => self::url('scam-help')], ['text' => '⭐ '.__('قيّم قناة'), 'url' => self::url('providers.index')]],
            ]],
        ]);

        TelegramAppLogin::api('sendMessage', [
            'chat_id' => $chatId,
            'text' => '👇 '.__('أو استخدم الأزرار تحت للوصول السريع.'),
            'reply_markup' => self::keyboard(),
        ]);
    }

    /** The bot's own descriptions: the long one shows in an empty chat before Start, the short one on its profile and when it's shared */
    public static function register(): void
    {
        $site = config('app.name');

        TelegramAppLogin::api('setMyDescription', ['description' => mb_substr(
            "🔎 افحص أي قناة توصيات على تيليجرام مجاناً: هل جمهورها حقيقي؟ هل تنشر صفقاتها قبل حدوثها؟ هل تذكر خساراتها؟\n\n"
            ."🔔 تابع القنوات، وأنبّهك إذا حذفت صفقات أو غيّرت اسمها أو ظهر من ينتحلها.\n\n"
            ."🛟 نُصب عليك؟ اعرف ماذا تفعل الآن، وبلّغ لنجمع بلاغك مع بلاغات الآخرين.\n\n"
            ."— {$site}", 0, 512)]);

        TelegramAppLogin::api('setMyShortDescription', ['short_description' => mb_substr(
            "افحص قنوات التوصيات مجاناً، وتابعها، واعرف ماذا تفعل إذا نُصب عليك. {$site}", 0, 120)]);
    }

    /** A site page, marked as coming from the bot */
    protected static function url(string $route): string
    {
        return route($route, ['utm_source' => 'telegram', 'utm_medium' => 'bot']);
    }
}
