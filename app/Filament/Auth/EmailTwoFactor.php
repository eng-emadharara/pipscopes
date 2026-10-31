<?php

namespace App\Filament\Auth;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Solutionforest\FilamentEmail2fa\Exceptions\InvalidTwoFACodeException;
use Solutionforest\FilamentEmail2fa\Interfaces\RequireTwoFALogin;
use Solutionforest\FilamentEmail2fa\Pages\TwoFactorAuth;

/**
 * The emailed code asked for after every admin sign-in (config filament-email-2fa.2fa_page).
 * On top of the package's page: a limit on wrong codes and on emails sent, the account always
 * taken from the session (the package looked it up by an email the browser could change), and
 * a code sent on arrival, since admins who sign in on the site (password, Google, Telegram)
 * never pass through Filament's login response that sends it.
 */
class EmailTwoFactor extends TwoFactorAuth
{
    public const MAX_WRONG_CODES = 5;

    public const MAX_EMAILS = 3;

    public const DECAY_SECONDS = 900;

    #[Locked]
    public string $email;

    public function mount()
    {
        if (! ($user = $this->getUser()) || $user->isTwoFaVerfied()) {
            return redirect(Filament::getUrl());
        }

        // Telegram-only accounts have no email
        $this->email = (string) $user->email;

        if (! $user->email) {
            $this->addError('data.2fa_code', __('لا يوجد بريد إلكتروني في هذا الحساب لإرسال رمز التحقق. أضف بريداً من صفحة حسابك أو ادخل بحساب آخر.'));

            return;
        }

        if (! $user->latest_2fa_code) {
            $this->sendCode($user);
        }
    }

    public function resend()
    {
        if (! $this->sendCode($this->getUser())) {
            return;
        }

        session()->flash('resent-success', __('filament-email-2fa::filament-email-2fa.resend_success'));
    }

    public function save()
    {
        $user = $this->getUser();
        $key = $this->limiterKey('verify', $user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_WRONG_CODES)) {
            $this->addError('data.2fa_code', $this->waitMessage($key));

            return;
        }

        try {
            $user->verify2FACode($this->data['2fa_code'] ?? '');
        } catch (InvalidTwoFACodeException $e) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            // Out of tries: the code can't be guessed any further, a new one is needed after the wait
            if (RateLimiter::tooManyAttempts($key, self::MAX_WRONG_CODES)) {
                $user->twoFaCodes()->delete();
                $this->addError('data.2fa_code', $this->waitMessage($key));
            } else {
                $this->addError('data.2fa_code', $e->getMessage());
            }

            return;
        }

        RateLimiter::clear($key);
        $user->twoFaVerifis()->create(['session_id' => session()->getId()]);

        // Straight on to where they were heading; the package's "Login Success" page is an extra click
        return redirect()->intended(Filament::getUrl());
    }

    /** Always the signed-in admin, never an account named by the browser. */
    public function getUser()
    {
        $user = Filament::auth()->user();

        return $user instanceof RequireTwoFALogin ? $user : null;
    }

    protected function sendCode($user): bool
    {
        $key = $this->limiterKey('email', $user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_EMAILS)) {
            $this->addError('data.2fa_code', $this->waitMessage($key));

            return false;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
        $user->send2FAEmail();

        return true;
    }

    protected function limiterKey(string $action, $user): string
    {
        return 'admin-2fa:'.$action.':'.$user->getKey();
    }

    protected function waitMessage(string $key): string
    {
        return __('محاولات كثيرة. حاول مجدداً بعد :minutes دقيقة.', [
            'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
        ]);
    }
}
