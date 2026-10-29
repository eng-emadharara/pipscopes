<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;

/**
 * Which member notifications go out, and where. Three switches must all be on:
 * the type in Admin → إعدادات الإشعارات, the channel there too, and the member's own choice
 * (Profile → الإشعارات). Email needs a confirmed address; Telegram needs a linked account
 * that hasn't blocked the bot.
 */
class NotificationPrefs
{
    public const SETTING = 'notifications';

    /** type => [label, description] — shown to members and in the admin panel */
    public const TYPES = [
        'review_status' => ['حالة تقييمك', 'عند نشر تقييمك أو عدم نشره بعد المراجعة.'],
        'owner_reply' => ['ردود أصحاب القنوات', 'عندما يرد صاحب القناة على تقييمك.'],
        'announcements' => ['أخبار وإعلانات المنصة', 'ميزات جديدة ومسابقات ونصائح من فريقنا، برسائل قليلة في الأسبوع.'],
    ];

    /** Announcements a member gets at most in 7 days, unless the admin sets another number */
    public const DEFAULT_WEEKLY_CAP = 2;

    public const CHANNELS = ['mail', 'telegram'];

    /** Admin switches: per type and per channel, all on by default */
    public static function siteEnabled(string $key): bool
    {
        return (bool) data_get(Setting::get(self::SETTING, []), $key, true);
    }

    /**
     * The member's own choice. Notifications about their activity are on until they switch them off;
     * for announcements the admin picks the starting value (Admin → الإشعارات).
     */
    public static function wants(User $user, string $type, string $channel): bool
    {
        $default = $type === 'announcements' ? self::siteEnabled('announcements_default') : true;

        return (bool) data_get($user->notification_prefs ?? [], "$type.$channel", $default);
    }

    /** 0 = no limit */
    public static function weeklyCap(): int
    {
        return (int) data_get(Setting::get(self::SETTING, []), 'weekly_cap', self::DEFAULT_WEEKLY_CAP);
    }

    public static function canReach(User $user, string $channel): bool
    {
        return match ($channel) {
            'mail' => filled($user->email) && $user->hasVerifiedEmail(),
            'telegram' => self::telegramChatId($user) !== null && ! $user->telegram_blocked_at
                && filled(config('services.telegram.bot_token')),
            default => false,
        };
    }

    /** Laravel notification channels for this member and type */
    public static function channels(User $user, string $type): array
    {
        if (! self::siteEnabled("types.$type")) {
            return [];
        }

        return collect(self::CHANNELS)
            ->filter(fn ($channel) => self::siteEnabled("channels.$channel") && self::wants($user, $type, $channel) && self::canReach($user, $channel))
            ->map(fn ($channel) => $channel === 'telegram' ? TelegramChannel::class : 'mail')
            ->values()->all();
    }

    /** The member's Telegram user id, which is also their private chat with the bot */
    public static function telegramChatId(User $user): ?string
    {
        $account = $user->relationLoaded('socialAccounts')
            ? $user->socialAccounts->firstWhere('provider', 'telegram')
            : $user->socialAccounts()->where('provider', 'telegram')->first();

        return $account?->provider_id;
    }
}
