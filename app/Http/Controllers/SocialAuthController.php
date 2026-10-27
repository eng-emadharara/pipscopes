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

    public function callback(Request $request, string $provider)
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        // The person pressed "Cancel" on the provider's screen
        if ($request->filled('error') || $request->filled('error_code')) {
            return redirect()->route('login')->with('error', __('تم إلغاء تسجيل الدخول عبر :provider.', ['provider' => SocialLogin::LABELS[$provider]]));
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social login failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->failed($provider);
        }

        return $this->signIn($request, $provider, [
            'id' => (string) $social->getId(),
            'name' => $social->getName(),
            'email' => $social->getEmail(),
            // Google says whether it verified the address; Facebook only shares confirmed ones
            'email_verified' => $provider === 'facebook' || filter_var($social->user['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'avatar' => $social->getAvatar(),
            'nickname' => $social->getNickname(),
        ]);
    }

    public function telegram(Request $request)
    {
        abort_unless(SocialLogin::isEnabled('telegram'), 404);

        $data = TelegramLogin::verify($request->only(TelegramLogin::FIELDS));

        if (! $data) {
            return $this->failed('telegram');
        }

        return $this->signIn($request, 'telegram', [
            'id' => $data['id'],
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => null,
            'email_verified' => false,
            'avatar' => $data['photo_url'] ?? null,
            'nickname' => $data['username'] ?? null,
        ]);
    }

    /** @param  array{id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string, nickname: ?string}  $profile */
    protected function signIn(Request $request, string $provider, array $profile)
    {
        $user = SocialLogin::resolveUser($provider, $profile);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => __('هذا البريد مسجّل مسبقاً. سجّل دخولك بكلمة المرور.')]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail() && ! $user->is_admin) {
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
