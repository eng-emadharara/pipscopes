<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Site identity and SEO settings edited in the admin panel (Settings → إعدادات الموقع).
 * Everything falls back to sensible defaults, so the site works before anything is set.
 * Uploaded files live on the "public" disk under site/.
 */
class Site
{
    public const KEY = 'site';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return (array) Setting::get(self::KEY, []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(self::all(), $key, $default);
    }

    /** A per-language text: the current language, then Arabic, then $default. */
    public static function text(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        $values = (array) self::get($key, []);
        $locale ??= app()->getLocale();
        $base = array_key_first(config('trust.locales'));

        return filled($values[$locale] ?? null) ? $values[$locale]
            : (filled($values[$base] ?? null) ? $values[$base] : $default);
    }

    public static function name(?string $locale = null): string
    {
        return self::text('name', config('trust.default_name', 'TrustTrader'), $locale);
    }

    /** Public URL of an uploaded file setting (logo, favicon, og_image), or null. */
    public static function fileUrl(string $key): ?string
    {
        $path = self::get($key);

        return $path && Storage::disk('public')->exists($path) ? asset('storage/'.$path) : null;
    }

    /** Absolute local path of an uploaded file setting, for the share-image generator. */
    public static function filePath(string $key): ?string
    {
        $path = self::get($key);

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    /** @return array{0: int, 1: int, mime: string}|null */
    public static function imageSize(string $key): ?array
    {
        $path = self::filePath($key);
        $size = $path ? @getimagesize($path) : false;

        return $size ? [$size[0], $size[1], 'mime' => $size['mime']] : null;
    }

    /** Changes whenever the name or logo changes, so generated share images are redrawn. */
    public static function brandVersion(): string
    {
        return substr(sha1(json_encode([self::get('name'), self::get('logo')])), 0, 10);
    }

    /** Platform's own social accounts shown in the footer: platform => url */
    public static function socials(): array
    {
        return array_filter((array) self::get('socials', []), 'filled');
    }

    /** Link that opens a direct chat with the platform on Telegram, or null. */
    public static function telegramContact(): ?string
    {
        $url = self::get('telegram_contact');

        return filled($url) ? $url : null;
    }

    /** Applies the configured name to the places that read config('app.name'): pages, emails, share images. */
    public static function applyToConfig(): void
    {
        $name = self::name();
        config(['app.name' => $name, 'mail.from.name' => $name]);
    }
}
