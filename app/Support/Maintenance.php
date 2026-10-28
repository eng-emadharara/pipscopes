<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Maintenance mode: while active, every public page shows the maintenance page (HTTP 503).
 * Admins, visitors who opened the private bypass link and allowed IP addresses see the
 * site as usual. The admin panel is never affected.
 */
class Maintenance
{
    /** Cookie set by the bypass link; holds a hash of the secret, so a new secret revokes old links */
    public const BYPASS_COOKIE = 'maintenance_bypass';

    /** Maintenance is switched on and, if it ends automatically, the end time hasn't passed yet. */
    public static function isActive(): bool
    {
        if (! Setting::get('maintenance_enabled', false)) {
            return false;
        }

        $endsAt = static::endsAt();

        return ! (static::endsAutomatically() && $endsAt && $endsAt->isPast());
    }

    /** Whether this request gets the maintenance page instead of the site. */
    public static function appliesTo(Request $request): bool
    {
        return static::isActive() && ! static::canSeeSite($request);
    }

    /** Admins, bypass-link holders and allowed IPs keep using the site during maintenance. */
    public static function canSeeSite(Request $request): bool
    {
        return (bool) $request->user()?->is_admin || static::hasBypass($request) || static::ipAllowed($request->ip());
    }

    /** Expected end in UTC; entered in the admin panel as a local time in the chosen time zone. */
    public static function endsAt(): ?Carbon
    {
        $value = Setting::get('maintenance_ends_at');
        if (! $value) {
            return null;
        }

        return Carbon::parse($value, Setting::get('maintenance_timezone') ?: config('app.timezone'))->utc();
    }

    public static function startedAt(): ?Carbon
    {
        $value = Setting::get('maintenance_started_at');

        return $value ? Carbon::parse($value) : null;
    }

    public static function showCountdown(): bool
    {
        return Setting::get('maintenance_show_countdown', true) && static::endsAt()?->isFuture();
    }

    public static function endsAutomatically(): bool
    {
        return (bool) Setting::get('maintenance_auto_end', false);
    }

    /** Seconds for the Retry-After header: until the expected end, or an hour when unknown. */
    public static function retryAfter(): int
    {
        $endsAt = static::endsAt();

        return $endsAt?->isFuture() ? max(60, (int) now()->diffInSeconds($endsAt)) : 3600;
    }

    /** Admin-edited page text in the current language, or the (already translated) default. */
    public static function text(string $key, string $default): string
    {
        $value = Setting::get('maintenance_texts', [])[app()->getLocale()][$key] ?? null;

        return filled($value) ? $value : $default;
    }

    public static function secret(): ?string
    {
        return Setting::get('maintenance_secret') ?: null;
    }

    /** Private link that lets its holder browse the site during maintenance. */
    public static function bypassUrl(): ?string
    {
        return static::secret() ? route('home', ['bypass' => static::secret()]) : null;
    }

    public static function bypassToken(): ?string
    {
        return static::secret() ? hash_hmac('sha256', static::secret(), config('app.key')) : null;
    }

    public static function hasBypass(Request $request): bool
    {
        $token = static::bypassToken();

        return $token && hash_equals($token, (string) $request->cookie(self::BYPASS_COOKIE));
    }

    /** @return list<string> IPs or CIDR ranges that always see the site */
    public static function allowedIps(): array
    {
        return array_values(array_filter((array) Setting::get('maintenance_allowed_ips', []), 'filled'));
    }

    public static function ipAllowed(?string $ip): bool
    {
        return $ip && static::allowedIps() && IpUtils::checkIp($ip, static::allowedIps());
    }
}
