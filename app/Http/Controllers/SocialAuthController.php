<?php

namespace App\Http\Controllers;

use App\Support\SocialLogin;
use App\Support\TelegramLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            // Lets people with several Google accounts pick the right one instead of reusing the last
            $driver->with(['prompt' => 'select_account']);
        }

        return $driver->redirect();
    }

    /** A signed-in member adds Google / Facebook from the profile page; the provider sends them back to callback() */
    public function linkRedirect(Request $request, string $provider)
    {
        abort_unless(SocialLogin::isEnabled($provider) && SocialLogin::isShown($provider), 404);

        $request->session()->put('social_link', $provider);

        return $this->redirect($provider);
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        // Signed in already: only a link started from the profile page is expected here
        $linking = $request->user() && $request->session()->pull('social_link') === $provider;
        if ($request->user() && ! $linking) {
            return redirect()->route('profile.edit');
        }

        // The person pressed "Cancel" on the provider's screen
        if ($request->filled('error') || $request->filled('error_code')) {
            return $linking
                ? redirect()->route('profile.edit')->with('error', __('تم إلغاء الربط مع :provider.', ['provider' => SocialLogin::LABELS[$provider]]))
                : redirect()->route('login')->with('error', __('تم إلغاء تسجيل الدخول عبر :provider.', ['provider' => SocialLogin::LABELS[$provider]]));
        }

        try {
            $driver = Socialite::driver($provider);

            if ($provider === 'facebook') {
                // Every field the basic public_profile + email permissions allow; gender and link need extra permissions and come back only if granted
                $driver->fields([
                    'id', 'name', 'first_name', 'middle_name', 'last_name', 'short_name', 'name_format',
                    'email', 'gender', 'verified', 'link', 'picture.width(1920)',
                ]);
            }

            $social = $driver->user();
        } catch (Throwable $e) {
            Log::warning('Social login failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $linking
                ? redirect()->route('profile.edit')->with('error', __('تعذّر الربط مع :provider. حاول مرة أخرى.', ['provider' => SocialLogin::LABELS[$provider]]))
                : $this->failed($provider);
        }

        $profile = [
            'id' => (string) $social->getId(),
            'name' => $social->getName(),
            'email' => $social->getEmail(),
            // Google says whether it verified the address; Facebook only shares confirmed ones
            'email_verified' => $provider === 'facebook' || filter_var($social->user['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'avatar' => $social->getAvatar(),
            'nickname' => $social->getNickname(),
            'raw' => $social->getRaw(),
            'token' => $social->token,
            'refresh_token' => $social->refreshToken,
            'expires_in' => $social->expiresIn ? (int) $social->expiresIn : null,
            'scopes' => array_values((array) $social->approvedScopes),
        ];

        if ($linking) {
            return SocialLogin::link($request->user(), $provider, $profile)
                ? redirect()->route('profile.edit')->with('success', __('تم ربط :provider بحسابك.', ['provider' => SocialLogin::LABELS[$provider]]))
                : redirect()->route('profile.edit')->with('error', __('حساب :provider هذا مرتبط بعضو آخر.', ['provider' => SocialLogin::LABELS[$provider]]));
        }

        return $this->signIn($request, $provider, $profile);
    }

    public function telegram(Request $request)
    {
        abort_unless(SocialLogin::isEnabled('telegram'), 404);

        $data = TelegramLogin::verify($request->only(TelegramLogin::FIELDS));

        if (! $data) {
            return $this->failed('telegram');
        }

        return $this->signIn($request, 'telegram', $this->telegramProfile($data));
    }

    /** A signed-in member adds Telegram to their account (which also confirms who they are). */
    public function linkTelegram(Request $request)
    {
        abort_unless(SocialLogin::isEnabled('telegram'), 404);

        $data = TelegramLogin::verify($request->only(TelegramLogin::FIELDS));

        if (! $data) {
            return back()->with('error', __('تعذّر ربط حساب تيليجرام. حاول مرة أخرى.'));
        }

        if (! SocialLogin::link($request->user(), 'telegram', $this->telegramProfile($data))) {
            return back()->with('error', __('حساب تيليجرام هذا مرتبط بعضو آخر.'));
        }

        $this->telegramReachable($request->user(), 'telegram');

        return back()->with('success', __('تم ربط حساب تيليجرام بنجاح.'));
    }

    /** Signing in with Telegram grants the bot permission to write again, after a block (App\Notifications\Channels\TelegramChannel) */
    protected function telegramReachable($user, string $provider): void
    {
        if ($provider === 'telegram' && $user->telegram_blocked_at) {
            $user->forceFill(['telegram_blocked_at' => null])->saveQuietly();
        }
    }

    /**
     * @param  array<string, string>  $data
     * @return array{id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string, nickname: ?string, raw: array<string, mixed>, token: ?string, refresh_token: ?string, expires_in: ?int, scopes: list<string>}
     */
    protected function telegramProfile(array $data): array
    {
        return [
            'id' => $data['id'],
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => null,
            'email_verified' => false,
            'avatar' => $data['photo_url'] ?? null,
            'nickname' => $data['username'] ?? null,
            // Telegram has no tokens; its signed fields are the whole payload
            'raw' => $data,
            'token' => null,
            'refresh_token' => null,
            'expires_in' => null,
            'scopes' => [],
        ];
    }

    /** @param  array{id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string, nickname: ?string, raw: array<string, mixed>, token: ?string, refresh_token: ?string, expires_in: ?int, scopes: list<string>}  $profile */
    protected function signIn(Request $request, string $provider, array $profile)
    {
        $user = SocialLogin::resolveUser($provider, $profile);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => __('هذا البريد مسجّل مسبقاً. سجّل دخولك بكلمة المرور.')]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->telegramReachable($user, $provider);

        if (! $user->isVerifiedMember() && ! $user->is_admin) {
            return redirect()->route('verification.notice')->with('success', $user->email
                ? __('أهلاً :name! بقيت خطوة واحدة: أكّد بريدك الإلكتروني.', ['name' => $user->name])
                : __('أهلاً :name! أضف بريدك الإلكتروني لتتمكن من كتابة التقييمات.', ['name' => $user->name]));
        }

        return redirect()->intended(route('home'))->with('success', $user->wasRecentlyCreated
            ? __('أهلاً بك :name! تم إنشاء حسابك.', ['name' => $user->name])
            : __('مرحباً بعودتك :name!', ['name' => $user->name]));
    }

    protected function failed(string $provider)
    {
        return redirect()->route('login')->with('error', __('تعذّر تسجيل الدخول عبر :provider. حاول مرة أخرى.', ['provider' => SocialLogin::LABELS[$provider]]));
    }
}
