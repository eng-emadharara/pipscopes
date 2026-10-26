<?php

namespace App\Support;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** 6-digit email verification codes sent after sign-up. */
class EmailVerification
{
    public const CODE_TTL_MINUTES = 30;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Generates a new code and emails it. Returns false when the email could not be sent. */
    public static function send(User $user): bool
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'verification_code' => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'verification_code_sent_at' => now(),
            'verification_attempts' => 0,
        ])->save();

        try {
            Mail::to($user)->locale(app()->getLocale())->send(new VerificationCodeMail($user, $code));

            return true;
        } catch (Throwable $e) {
            Log::error('Verification email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Seconds left before another code may be requested. */
    public static function resendWait(User $user): int
    {
        if (! $user->verification_code_sent_at) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $user->verification_code_sent_at->diffInSeconds(now()));
    }

    /**
     * Checks a submitted code.
     *
     * @return 'ok'|'invalid'|'expired'|'too_many'
     */
    public static function verify(User $user, string $code): string
    {
        if (! $user->verification_code || $user->verification_code_expires_at?->isPast()) {
            return 'expired';
        }

        if ($user->verification_attempts >= self::MAX_ATTEMPTS) {
            return 'too_many';
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $user->verification_code)) {
            $user->increment('verification_attempts');

            return $user->verification_attempts >= self::MAX_ATTEMPTS ? 'too_many' : 'invalid';
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
        ])->save();

        return 'ok';
    }
}
