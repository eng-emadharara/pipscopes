<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Wrong passwords per account, whatever IP they come from (the route throttle only limits each IP),
 * so a password list can't be tried against one member from many addresses.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 10;

    public const DECAY_SECONDS = 900;

    /** Seconds until this account can be tried again, or 0 when it's not locked. */
    public static function wait(string $guard, string $email): int
    {
        $key = self::key($guard, $email);

        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    public static function failed(string $guard, string $email): void
    {
        RateLimiter::hit(self::key($guard, $email), self::DECAY_SECONDS);
    }

    public static function clear(string $guard, string $email): void
    {
        RateLimiter::clear(self::key($guard, $email));
    }

    protected static function key(string $guard, string $email): string
    {
        return 'login:'.$guard.':'.sha1(Str::lower(trim($email)));
    }
}
