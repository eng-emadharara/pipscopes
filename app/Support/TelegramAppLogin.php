<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Signing in through the Telegram app instead of the browser popup, which asks for a phone number.
 * The button opens t.me/<bot>?start=login_<token>, the token being a secret of this browser's session. The member
 * presses Start, then Confirm in the chat; the page keeps asking the site until that confirmation arrives.
 * The Confirm step matters: without it, whoever sends someone a link holding their own token would be signed in as them.
 * Switched on and off in Admin → مفاتيح تسجيل الدخول; when off, the popup is used as before.
 */
class TelegramAppLogin
{
    public const SETTING = 'telegram_app_login';

    public const SESSION_KEY = 'telegram_app_token';

    /** How long a Confirm button and a confirmation stay valid */
    public const VALID_MINUTES = 5;

    public static function isOn(): bool
    {
        return (bool) data_get(Setting::get(self::SETTING), 'enabled')
            && filled(data_get(Setting::get(self::SETTING), 'bot_username'))
            && SocialLogin::isEnabled('telegram');
    }

    public static function botUsername(): ?string
    {
        return data_get(Setting::get(self::SETTING), 'bot_username') ?: null;
    }

    public static function deepLink(): string
    {
        $token = session(self::SESSION_KEY);
        if (! $token) {
            $token = Str::random(40);
            session([self::SESSION_KEY => $token]);
        }

        return 'https://t.me/'.data_get(Setting::get(self::SETTING), 'bot_username').'?start=login_'.$token;
    }

    /** What turns a button into "open the app and wait": read by resources/js/telegram-app.js */
    public static function linkAttributes(string $fallbackAction): HtmlString
    {
        return new HtmlString(collect([
            'href' => self::deepLink(),
            'target' => '_blank',
            'rel' => 'noopener',
            'data-telegram-app' => '',
            'data-status-url' => route('social.telegram.app-status'),
            'data-bot-id' => TelegramLogin::botId(),
            'data-action' => $fallbackAction,
            'data-csrf' => csrf_token(),
            'data-wait-text' => __('اضغط Start ثم «تأكيد الدخول» في تيليجرام، ثم ارجع إلى هنا.'),
            'data-fallback-text' => __('ما انفتح التطبيق؟ استخدم رقم الجوال'),
            'data-popup-error' => __('تعذّر فتح نافذة تيليجرام. اسمح بالنوافذ المنبثقة وحاول مرة أخرى.'),
        ])->map(fn ($value, $name) => $name.'="'.e($value).'"')->implode(' '));
    }

    public static function webhookSecret(): string
    {
        return hash_hmac('sha256', 'telegram-webhook', (string) config('app.key'));
    }

    public static function api(string $method, array $params = []): array
    {
        $response = Http::timeout(15)->post('https://api.telegram.org/bot'.config('services.telegram.bot_token')."/$method", $params);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new RuntimeException((string) ($response->json('description') ?: "Telegram $method failed"));
        }

        return (array) $response->json('result');
    }

    /** Connects the bot to this site. Refuses to take a bot away from another service that receives its messages. */
    public static function enable(): void
    {
        $url = route('telegram.webhook');
        $current = (string) (self::api('getWebhookInfo')['url'] ?? '');

        if ($current !== '' && $current !== $url) {
            throw new RuntimeException('البوت مربوط بخدمة أخرى تستقبل رسائله: '.(parse_url($current, PHP_URL_HOST) ?: $current));
        }

        $username = (string) (self::api('getMe')['username'] ?? '');
        self::api('setWebhook', [
            'url' => $url,
            'secret_token' => self::webhookSecret(),
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        Setting::put([self::SETTING => ['enabled' => true, 'bot_username' => $username]]);

        // The bot's menu (the "/" button in Telegram): what it can do
        rescue(fn () => self::api('setMyCommands', ['commands' => [
            ['command' => 'channels', 'description' => 'القنوات التي أتابعها'],
            ['command' => 'start', 'description' => 'كيف أفحص قناة توصيات'],
            ['command' => 'scam', 'description' => 'نُصب عليّ، ماذا أفعل؟'],
        ]]), report: false);
        // What it says about itself before Start, and on its profile (BotMenu)
        rescue(fn () => BotMenu::register(), report: false);
    }

    public static function disable(): void
    {
        Setting::put([self::SETTING => ['enabled' => false]]);
        self::api('deleteWebhook');
    }

    /** One update from Telegram (App\Http\Controllers\TelegramWebhookController) */
    public static function handle(array $update): void
    {
        if ($callback = $update['callback_query'] ?? null) {
            self::handleCallback($callback);
        } elseif (data_get($update, 'message.chat.type') === 'private' && is_string($text = data_get($update, 'message.text'))) {
            self::useArabic();
            self::handleMessage((int) $update['message']['chat']['id'], $text);
        }
    }

    /** The bot always answers in Arabic: the site and its ads are Arabic, while many Arab traders keep Telegram in English */
    protected static function useArabic(): void
    {
        app()->setLocale('ar');
    }

    protected static function handleMessage(int $chatId, string $text): void
    {
        if (preg_match('/^\/start\s+login_([A-Za-z0-9]{40})$/', trim($text), $m)) {
            self::api('sendMessage', [
                'chat_id' => $chatId,
                'text' => __('🔐 طلب دخول إلى موقع :site', ['site' => config('app.name')])."\n\n"
                    .__('اضغط «تأكيد الدخول» فقط إذا طلبته أنت الآن من جهازك. إذا أرسل لك أحد هذا الرابط فاضغط «إلغاء».'),
                'reply_markup' => ['inline_keyboard' => [
                    [['text' => '✅ '.__('تأكيد الدخول'), 'callback_data' => 'tgl:ok:'.$m[1]]],
                    [['text' => '❌ '.__('إلغاء'), 'callback_data' => 'tgl:no:'.$m[1]]],
                ]],
            ]);
        } elseif (preg_match('/^\/start\s+ads_([a-z0-9]{1,20})$/', trim($text), $m)) {
            // Telegram Ads may only link to Telegram, so its ads open this bot; the button carries the ad's name to the site's analytics.
            // A "check" ad (ads_check…) is answered with the check itself: how it works and what following brings (ChannelCheckBot)
            $check = str_starts_with($m[1], 'check');
            self::api('sendMessage', [
                'chat_id' => $chatId,
                'text' => $check
                    ? ChannelCheckBot::welcome()
                    : __('👋 أهلاً بك في :site.', ['site' => config('app.name')])."\n\n"
                        .__('جرّبت قناة توصيات؟ ابحث عنها واكتب تقييمك مجاناً، ويمكنك إخفاء اسمك. تجربتك قد تحمي متداولاً قبل أن يدفع.'),
                'reply_markup' => ['inline_keyboard' => [
                    [$check
                        ? ['text' => '🔍 '.__('افحص من الموقع'), 'url' => self::adUrl($m[1])]
                        : ['text' => '⭐ '.__('قيّم قناة'), 'url' => self::adUrl($m[1])]],
                ]],
            ]);
        } elseif (preg_match('/^(\/scam(@\w+)?|نصب)$/u', trim($text))) {
            // "/scam": where to start after being scammed (ScamHelpController)
            BotMenu::scam($chatId);
        } elseif (BotMenu::handles($chatId, $text)) {
            // One of the keyboard buttons under the message box (BotMenu)
        } elseif (ChannelWatching::handlesList($chatId, $text)) {
            // "/channels": what this chat follows (ChannelWatching)
        } elseif (ChannelWatching::handlesStart($chatId, $text)) {
            // Following a channel from the site's link (ChannelWatching)
        } elseif (ChannelCheckBot::handles($chatId, $text)) {
            // A channel link or @name: the report goes out once Telegram has its answer (ChannelCheckBot)
        } elseif (str_starts_with(trim($text), '/start')) {
            BotMenu::welcome($chatId);
        } else {
            // Anything else: what the bot does, with the keyboard (members from before it existed get it here)
            self::api('sendMessage', ['chat_id' => $chatId, 'text' => ChannelCheckBot::help(), 'reply_markup' => BotMenu::keyboard()]);
        }
    }

    /** The channel list (the channel check, for a "check" ad), tagged with the Telegram ad that brought the visitor (t.me/<bot>?start=ads_<name>) */
    public static function adUrl(string $ad): string
    {
        $check = str_starts_with($ad, 'check');

        return route($check ? 'channel-check' : 'providers.index', [
            'utm_source' => 'telegram',
            'utm_medium' => 'paid',
            'utm_campaign' => $check ? 'check' : 'rate_test',
            'utm_content' => $ad,
        ]);
    }

    protected static function handleCallback(array $callback): void
    {
        self::useArabic();

        // Follow / stop following a channel (ChannelWatching)
        if (ChannelWatching::handlesCallback($callback)) {
            return;
        }

        if (! preg_match('/^tgl:(ok|no):([A-Za-z0-9]{40})$/', (string) ($callback['data'] ?? ''), $m)) {
            return;
        }

        $expired = (int) data_get($callback, 'message.date', 0) < now()->subMinutes(self::VALID_MINUTES)->timestamp;

        $returnUrl = null;

        if ($m[1] === 'ok' && ! $expired) {
            $identity = array_map('strval', array_filter(
                array_intersect_key($callback['from'], array_flip(['id', 'first_name', 'last_name', 'username'])), 'filled',
            ));

            DB::table('telegram_app_logins')->where('created_at', '<', now()->subDay())->delete();
            DB::table('telegram_app_logins')->updateOrInsert(['token' => $m[2]], [
                'data' => json_encode($identity),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $returnUrl = self::returnUrl($m[2], $identity);
        }

        self::api('answerCallbackQuery', ['callback_query_id' => $callback['id']]);

        $reply = [
            'chat_id' => data_get($callback, 'message.chat.id'),
            'message_id' => data_get($callback, 'message.message_id'),
            'text' => match (true) {
                $m[1] === 'no' => __('تم الإلغاء، ولم يتم تسجيل أي دخول.'),
                $expired => __('انتهت صلاحية هذا الطلب. ارجع إلى الموقع وحاول مرة أخرى.'),
                default => '✅ '.__('تم التأكيد. ارجع إلى الموقع وستجد نفسك مسجّلاً.'),
            },
        ];

        try {
            self::api('editMessageText', $reply + ($returnUrl ? ['reply_markup' => ['inline_keyboard' => [
                [['text' => '🌐 '.__('ارجع للموقع'), 'url' => $returnUrl]],
            ]]] : []));
        } catch (RuntimeException) {
            // Telegram refuses some addresses for buttons; the browser that asked signs in by itself anyway
            self::api('editMessageText', $reply);
        }
    }

    /** Called while the page waits: remembers where this browser was heading, for the bot's "back to the site" button */
    public static function rememberWaiting(Request $request): void
    {
        if ($token = $request->session()->get(self::SESSION_KEY)) {
            $member = (bool) $request->user();
            Cache::put("telegram-app-waiting:$token", [
                'member' => $member,
                'url' => $member ? url()->previous() : $request->session()->get('url.intended', route('home')),
            ], now()->addMinutes(self::VALID_MINUTES * 2));
        }
    }

    /**
     * Where the bot's button goes. Signing in: a link that works once, for a few minutes. It is only ever sent
     * in the member's own chat, after they confirmed. Linking to an account: just the page, since
     * signing that identity in could open a second account before the link is saved.
     *
     * @param  array<string, string>  $identity
     */
    protected static function returnUrl(string $token, array $identity): string
    {
        $waiting = Cache::get("telegram-app-waiting:$token", []);
        $url = self::safeUrl($waiting['url'] ?? null);

        if ($waiting['member'] ?? false) {
            return $url;
        }

        $key = Str::random(40);
        Cache::put("telegram-app-return:$key", ['identity' => $identity, 'url' => $url], now()->addMinutes(self::VALID_MINUTES));

        return URL::temporarySignedRoute('social.telegram.app-return', now()->addMinutes(self::VALID_MINUTES), ['key' => $key]);
    }

    /** Only pages of this site */
    protected static function safeUrl(?string $url): string
    {
        return $url && ($url === url('/') || str_starts_with($url, url('/').'/')) ? $url : route('home');
    }

    /**
     * What the button's link stands for, used once.
     *
     * @return array{identity: array<string, string>, url: string}|null
     */
    public static function pullReturn(string $key): ?array
    {
        return Cache::pull("telegram-app-return:$key");
    }

    /**
     * The Telegram identity confirmed for this browser, used once.
     *
     * @return array<string, string>|null
     */
    public static function pull(Request $request): ?array
    {
        $token = $request->session()->get(self::SESSION_KEY);
        if (! $token) {
            return null;
        }

        $row = DB::table('telegram_app_logins')->where('token', $token)
            ->where('created_at', '>=', now()->subMinutes(self::VALID_MINUTES))->first();
        if (! $row) {
            return null;
        }

        DB::table('telegram_app_logins')->where('id', $row->id)->delete();
        $request->session()->forget(self::SESSION_KEY);

        return json_decode($row->data, true);
    }
}
