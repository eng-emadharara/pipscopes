<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\ProviderSubmission;
use Illuminate\Support\Str;

/** Helpers for the add-channel form: forgiving link input, duplicate detection. */
class SubmissionLinks
{
    /**
     * "@gold", "t.me/gold" and "https://t.me/gold" all become "https://t.me/gold".
     * Anything that isn't a Telegram link is returned as typed, so validation can reject it.
     */
    public static function telegram(?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^@?([A-Za-z0-9_]{3,})$/', $value, $m)) {
            return 'https://t.me/'.$m[1];
        }

        $value = self::url($value);
        $host = Str::lower(preg_replace('/^www\./i', '', (string) parse_url($value, PHP_URL_HOST)));

        return in_array($host, ['t.me', 'telegram.me', 'telegram.dog'], true)
            ? 'https://t.me/'.ltrim((string) parse_url($value, PHP_URL_PATH), '/')
            : $value;
    }

    /** Adds the missing "https://" so people can paste "instagram.com/name". */
    public static function url(?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '') {
            return null;
        }

        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) ? $value : 'https://'.ltrim($value, '/');
    }

    public static function isTelegram(?string $url): bool
    {
        return (bool) preg_match('#^https://t\.me/[^/\s]+#', (string) $url);
    }

    /** The provider already on the platform with this Telegram channel, if any. */
    public static function existingProvider(string $telegramUrl): ?Provider
    {
        $key = AccountHandle::parse($telegramUrl)['key'] ?? null;
        if (! $key) {
            return null;
        }

        return Provider::whereNotNull('telegram_url')->get(['id', 'name', 'slug', 'telegram_url', 'is_active'])
            ->first(fn (Provider $p) => (AccountHandle::parse($p->telegram_url)['key'] ?? null) === $key);
    }

    /** True when someone already sent this channel and it's waiting for review. */
    public static function pendingSubmission(string $telegramUrl): bool
    {
        $key = AccountHandle::parse($telegramUrl)['key'] ?? null;

        return $key && ProviderSubmission::where('status', 'new')->whereNotNull('telegram_url')->pluck('telegram_url')
            ->contains(fn (string $url) => (AccountHandle::parse($url)['key'] ?? null) === $key);
    }
}
