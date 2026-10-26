<?php

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Support\OwnerPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Channel owners can't write reviews on any channel: not their own (conflict of interest)
 * and not others' (keeps reviews neutral). Runs before "auth" so owners get a clear message
 * instead of being sent to the members' login page.
 */
class BlockChannelOwners
{
    public function handle(Request $request, Closure $next): Response
    {
        $owner = OwnerPortal::owner();
        if (! $owner) {
            return $next($request);
        }

        // This runs before route model binding (it must precede "auth"), so the slug may not be resolved yet
        $provider = $request->route('provider');
        if (is_string($provider)) {
            $provider = Provider::where('slug', $provider)->first();
        }

        $message = $provider && $owner->provider_id === $provider->id
            ? __('لا يمكنك تقييم قناتك لأنك صاحبها. يمكنك الرد على التقييمات من هذه الصفحة.')
            : __('لا يمكن لأصحاب القنوات كتابة تقييمات للقنوات الأخرى، حفاظاً على حيادية التقييمات.');

        return $provider
            ? redirect()->route('providers.show', $provider)->with('error', $message)
            : redirect()->route('home')->with('error', $message);
    }
}
