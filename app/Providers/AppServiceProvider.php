<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Market;
use App\Models\Page;
use App\Support\SocialKeys;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The .env name, used until a name is set in the admin panel (App\Support\Site)
        config(['trust.default_name' => config('app.name')]);

        // Google / Facebook / Telegram keys come only from the admin panel
        SocialKeys::apply();

        // Queued emails and Telegram messages use the site name from the admin panel, as web pages do
        Queue::before(fn () => \App\Support\Site::applyToConfig());

        // Translated, RTL-aware pagination for the public site (Filament uses its own)
        Paginator::defaultView('pagination.site');

        // Shown to admins next to each owner account
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'owner') {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }

            // When the password / Google / Telegram was actually used, not the remember cookie;
            // the admin panel asks for that again after a few days (App\Http\Middleware\LimitAdminSession)
            if ($event->guard === 'web' && ! auth()->guard('web')->viaRemember()) {
                session(['signed_in_at' => now()->timestamp]);
            }
        });

        // Re-sends the remember cookie with a fresh expiry, so a member who keeps visiting never has to sign in again
        // (App\Http\Middleware\RenewRememberCookie). A macro, so it can use the guard's own cookie format.
        SessionGuard::macro('renewRecaller', function () {
            /** @var SessionGuard $this */
            if ($user = $this->user()) {
                $this->ensureRememberTokenIsSet($user);
                $this->queueRecallerCookie($user);
            }
        });

        // The add-provider form is used on /submit and on the pre-launch page
        View::composer('submissions._form', function ($view) {
            $view->with([
                'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
                'markets' => Market::active()->get(),
            ]);
        });

        // Content pages linked from the public header/footer
        View::composer('components.layouts.app', function ($view) {
            $view->with('navPages', Page::published()
                ->where(fn ($q) => $q->where('show_in_header', true)->orWhere('show_in_footer', true))
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'title', 'translations', 'show_in_header', 'show_in_footer']));
        });
    }
}
