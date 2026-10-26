<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns whatever a user pastes (a link, "@name", a phone number) into a stable key per
 * platform, so "https://t.me/GoldPro", "t.me/goldpro" and "@goldpro" all match.
 */
class AccountHandle
{
    /** Platform keys; labels are in lang/{locale}/trust.php under account_platforms. */
    public const PLATFORMS = ['telegram', 'instagram', 'x', 'tiktok', 'youtube', 'facebook', 'whatsapp', 'website', 'other'];

    /** Icon names for the ui-icon component */
    public const ICONS = [
        'telegram' => 'telegram', 'instagram' => 'instagram', 'x' => 'x', 'tiktok' => 'tiktok',
        'youtube' => 'youtube', 'facebook' => 'facebook', 'whatsapp' => 'whatsapp', 'website' => 'globe', 'other' => 'alert',
    ];

    protected const HOSTS = [
        't.me' => 'telegram', 'telegram.me' => 'telegram', 'telegram.dog' => 'telegram',
        'instagram.com' => 'instagram', 'x.com' => 'x', 'twitter.com' => 'x',
        'tiktok.com' => 'tiktok', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube', 'm.youtube.com' => 'youtube',
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'm.facebook.com' => 'facebook',
        'wa.me' => 'whatsapp', 'api.whatsapp.com' => 'whatsapp',
    ];

    /** Platforms identified by a username (first path segment of their URLs) */
    public const USERNAME_PLATFORMS = ['telegram', 'instagram', 'x', 'tiktok', 'youtube', 'facebook'];

    /**
     * @return array{platform: string, key: string, display: string}|null
     */
    public static function parse(?string $input, ?string $platform = null): ?array
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        $lower = Str::lower($raw);

        // "goldpro.official" is a valid Instagram/TikTok username, so a bare dotted name only
        // counts as a website when no username-based platform was chosen
        $explicitUrl = (bool) preg_match('#^(https?://|www\.)#', $lower) || str_contains($lower, '/');
        $domainLike = (bool) preg_match('#^[a-z0-9-]+(\.[a-z0-9-]+)+$#', $lower);
        $looksLikeUrl = $explicitUrl || ($domainLike && ! in_array($platform, self::USERNAME_PLATFORMS, true));

        if ($looksLikeUrl) {
            $url = preg_match('#^https?://#', $lower) ? $lower : 'https://'.$lower;
            $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $detected = self::HOSTS[$host] ?? null;

            if ($detected === 'whatsapp') {
                $digits = preg_replace('/\D/', '', $path ?: (string) parse_url($url, PHP_URL_QUERY));

                return $digits ? self::make('whatsapp', $digits) : null;
            }

            if ($detected) {
                // youtube.com/@name or /c/name, tiktok.com/@name: use the part that names the account
                $segments = array_values(array_filter(explode('/', $path)));
                $name = in_array($segments[0] ?? '', ['c', 'channel', 'user'], true) ? ($segments[1] ?? '') : ($segments[0] ?? '');
                $name = ltrim($name, '@');

                return $name !== '' ? self::make($detected, $name) : null;
            }

            $site = rtrim($host.'/'.$path, '/');

            return self::make($platform === 'other' ? 'other' : 'website', $site);
        }

        $platform = in_array($platform, self::PLATFORMS, true) ? $platform : 'telegram';

        if ($platform === 'whatsapp') {
            $digits = preg_replace('/\D/', '', $raw);

            return $digits ? self::make('whatsapp', $digits) : null;
        }

        $name = ltrim(preg_replace('/\s+/', '', $lower), '@');

        return $name !== '' ? self::make($platform, $name) : null;
    }

    /** @return array{platform: string, key: string, display: string} */
    protected static function make(string $platform, string $name): array
    {
        $display = match (true) {
            in_array($platform, self::USERNAME_PLATFORMS, true) => '@'.$name,
            $platform === 'whatsapp' => '+'.$name,
            default => $name,
        };

        return ['platform' => $platform, 'key' => $platform.':'.$name, 'display' => $display];
    }
}
