<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel needs a real sign-in (password, Google or Telegram) every few days
 * (config trust.admin_session_days); the remember cookie that keeps members signed in isn't enough here.
 */
class LimitAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $signedInAt = (int) $request->session()->get('signed_in_at', 0);
        $days = (int) config('trust.admin_session_days');

        if ($days > 0 && $signedInAt < now()->subDays($days)->timestamp) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(Filament::getLoginUrl())
                ->with('status', __('لحماية لوحة التحكم، سجّل دخولك من جديد.'));
        }

        return $next($request);
    }
}
