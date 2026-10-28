<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Social sign-in keys edited in the admin panel (Settings → مفاتيح تسجيل الدخول).
 * Stored encrypted in the "social_keys" setting and put into config/services.php; .env is not read,
 * so a key left empty in the panel is simply not set.
 */
class SocialKeys
{
    public const KEY = 'social_keys';

    /** provider => [config field => [label, is secret]] */
    public const FIELDS = [
        'google' => [
            'client_id' => ['Client ID', false],
            'client_secret' => ['Client Secret', true],
        ],
        'facebook' => [
            'client_id' => ['App ID', false],
            'client_secret' => ['App Secret', true],
        ],
        'telegram' => [
            'bot_token' => ['Bot Token', true],
        ],
    ];

    /** Puts the panel's keys into config('services.*'); called once per request from AppServiceProvider. */
    public static function apply(): void
    {
        try {
            $stored = static::stored();
        } catch (Throwable) {
            // No settings table yet (fresh install, or before migrations run)
            return;
        }

        foreach ($stored as $provider => $values) {
            foreach ($values as $field => $value) {
                if (isset(self::FIELDS[$provider][$field]) && filled($value)) {
                    config(["services.$provider.$field" => $value]);
                }
            }
        }
    }

    /**
     * Decrypted keys saved in the panel; unreadable ones (e.g. after APP_KEY changed) are skipped.
     *
     * @return array<string, array<string, string>>
     */
    public static function stored(): array
    {
        $keys = [];

        foreach ((array) Setting::get(self::KEY, []) as $provider => $values) {
            foreach ((array) $values as $field => $encrypted) {
                if (! filled($encrypted)) {
                    continue;
                }
                try {
                    $keys[$provider][$field] = Crypt::decryptString($encrypted);
                } catch (DecryptException) {
                    // Saved with another APP_KEY; treated as not set until saved again
                }
            }
        }

        return $keys;
    }

    /** @param  array<string, array<string, ?string>>  $keys  empty values remove the saved key */
    public static function save(array $keys): void
    {
        $encrypted = [];

        foreach (self::FIELDS as $provider => $fields) {
            foreach (array_keys($fields) as $field) {
                $value = trim((string) ($keys[$provider][$field] ?? ''));
                if ($value !== '') {
                    $encrypted[$provider][$field] = Crypt::encryptString($value);
                }
            }
        }

        Setting::put([self::KEY => $encrypted]);
    }

    public static function isSet(string $provider, string $field): bool
    {
        return filled(static::stored()[$provider][$field] ?? null);
    }
}
