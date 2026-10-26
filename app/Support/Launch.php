<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Pre-launch mode: while active, visitors only see the launch page and the
 * "add a provider" form. Admins always see the full site.
 */
class Launch
{
    /** Pre-launch is switched on and, if auto-launch is set, the launch time hasn't passed yet. */
    public static function isPrelaunch(): bool
    {
        if (! Setting::get('prelaunch_enabled', false)) {
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
