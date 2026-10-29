<?php

namespace App\Http\Middleware;

use App\Support\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('trust.locales'));

        // ?lang=en lets shared links open in a given language
        if (in_array($request->query('lang'), $supported, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }

        // Arabic for everyone until they pick another language from the switcher
        $locale = $request->session()->get('locale') ?? config('app.locale', $supported[0]);

        app()->setLocale(in_array($locale, $supported, true) ? $locale : $supported[0]);

        // Remembered on the account, so emails and Telegram messages come in the same language
        if (($user = $request->user()) && $user->locale !== app()->getLocale()) {
            $user->forceFill(['locale' => app()->getLocale()])->saveQuietly();
        }

        // The site name can differ per language
        Site::applyToConfig();

        return $next($request);
    }
}
