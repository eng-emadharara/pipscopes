<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    protected static function api(string $method, array $params = []): array
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
            self::useLanguageOf($update['message']['from'] ?? []);
            self::handleMessage((int) $update['message']['chat']['id'], $text);
        }
    }

    protected static function useLanguageOf(array $from): void
    {
        app()->setLocale(str_starts_with((string) ($from['language_code'] ?? ''), 'en') ? 'en' : 'ar');
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
        } elseif (str_starts_with(trim($text), '/start')) {
            self::api('sendMessage', ['chat_id' => $chatId, 'text' => __('أهلاً بك في :site. ستصلك هنا إشعاراتك من الموقع.', ['site' => config('app.name')])]);
        }
    }

    protected static function handleCallback(array $callback): void
    {
        self::useLanguageOf($callback['from'] ?? []);

        if (! preg_match('/^tgl:(ok|no):([A-Za-z0-9]{40})$/', (string) ($callback['data'] ?? ''), $m)) {
            return;
        }

        $expired = (int) data_get($callback, 'message.date', 0) < now()->subMinutes(self::VALID_MINUTES)->timestamp;

        if ($m[1] === 'ok' && ! $expired) {
            DB::table('telegram_app_logins')->where('created_at', '<', now()->subDay())->delete();
            DB::table('telegram_app_logins')->updateOrInsert(['token' => $m[2]], [
                'data' => json_encode(array_map('strval', array_filter(
                    array_intersect_key($callback['from'], array_flip(['id', 'first_name', 'last_name', 'username'])), 'filled',
                ))),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        self::api('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        self::api('editMessageText', [
            'chat_id' => data_get($callback, 'message.chat.id'),
            'message_id' => data_get($callback, 'message.message_id'),
            'text' => match (true) {
                $m[1] === 'no' => __('تم الإلغاء، ولم يتم تسجيل أي دخول.'),
                $expired => __('انتهت صلاحية هذا الطلب. ارجع إلى الموقع وحاول مرة أخرى.'),
                default => '✅ '.__('تم التأكيد. ارجع إلى الموقع وستجد نفسك مسجّلاً.'),
            },
        ]);
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
