<?php

namespace App\Support;

/**
 * Checks data returned by the Telegram Login Widget.
 *
 * @see https://core.telegram.org/widgets/login#checking-authorization
 */
class TelegramLogin
{
    public const FIELDS = ['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash'];

    /** Older sign-ins are refused so a leaked payload can't be replayed later. */
    public const MAX_AGE_SECONDS = 86400;

    /** The bot's numeric id: the part of the token before the colon. */
    public static function botId(): ?string
    {
        $token = (string) config('services.telegram.bot_token');

        return preg_match('/^(\d+):[\w-]+$/', $token, $m) ? $m[1] : null;
    }

    /**
     * Returns the signed fields (without the hash), or null if they weren't signed by our bot or are too old.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public static function verify(array $data): ?array
    {
        $token = (string) config('services.telegram.bot_token');
        $hash = $data['hash'] ?? null;

        if (! self::botId() || ! is_string($hash) || $hash === '') {
            return null;
        }

        $fields = array_filter(
            array_intersect_key($data, array_flip(self::FIELDS)),
            fn ($value, $key) => $key !== 'hash' && is_scalar($value) && (string) $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );
        ksort($fields);

        $checkString = collect($fields)->map(fn ($value, $key) => "$key=$value")->implode("\n");
        $expected = hash_hmac('sha256', $checkString, hash('sha256', $token, true));

        if (! hash_equals($expected, strtolower($hash))) {
            return null;
        }

        $age = time() - (int) ($fields['auth_date'] ?? 0);
        if (empty($fields['id']) || $age > self::MAX_AGE_SECONDS || $age < -300) {
            return null;
        }

        return array_map('strval', $fields);
    }
}
