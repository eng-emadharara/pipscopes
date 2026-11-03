<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Before the official launch the site runs in one of two modes (Admin → إعدادات الإطلاق):
 * pre-launch, where visitors only see the launch page and the "add a provider" form (admins always see
 * the full site), or beta, where everything is open with a countdown to the launch on every page.
 */
class Launch
{
    /** The beta period: until the launch time, which ends it whatever the auto-launch switch says, since nothing is closed. */
    public static function isBeta(): bool
    {
        return Setting::get('prelaunch_enabled', false) && Setting::get('launch_mode') === 'beta' && ! static::launchAt()?->isPast();
    }

    /** Reviews written now publish straight away and are marked as early: the beta period, or a channel opened early. */
    public static function collectingEarly(Provider $provider): bool
    {
        return static::isBeta() || static::earlyOpen($provider);
    }

    /** Pre-launch is switched on and, if auto-launch is set, the launch time hasn't passed yet. */
    public static function isPrelaunch(): bool
    {
        if (! Setting::get('prelaunch_enabled', false) || Setting::get('launch_mode') === 'beta') {
            return false;
        }

        $launchAt = static::launchAt();

        return ! (Setting::get('auto_launch', false) && $launchAt && $launchAt->isPast());
    }

    /** Whether the current visitor gets the pre-launch experience (admins and channel owners never do). */
    public static function appliesToVisitor(): bool
    {
        // Owners may prepare their channel page (replies, details) before launch
        return static::isPrelaunch() && ! auth()->user()?->is_admin && ! OwnerPortal::owner();
    }

    /** Pre-launch, and this channel is open to visitors anyway so it can collect reviews at its owner's request. */
    public static function earlyOpen(?Provider $provider): bool
    {
        return $provider && $provider->is_active && $provider->is_early_open && static::isPrelaunch();
    }

    /** Launch moment in UTC; entered in the admin panel as a local time in the chosen time zone. */
    public static function launchAt(): ?Carbon
    {
        $value = Setting::get('launch_at');
        if (! $value) {
            return null;
        }

        return Carbon::parse($value, Setting::get('launch_timezone') ?: config('app.timezone'))->utc();
    }

    public static function showCountdown(): bool
    {
        return Setting::get('show_countdown', true) && static::launchAt()?->isFuture();
    }

    public static function opensAutomatically(): bool
    {
        return (bool) Setting::get('auto_launch', false);
    }

    /** Admin-edited launch page text in the current language, or the (already translated) default. */
    public static function text(string $key, string $default): string
    {
        $value = Setting::get('prelaunch_texts', [])[app()->getLocale()][$key] ?? null;

        return filled($value) ? $value : $default;
    }
}
