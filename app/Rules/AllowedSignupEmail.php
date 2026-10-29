<?php

namespace App\Rules;

use App\Support\SignupGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Rejects throwaway and dead-domain addresses, and a second account on the same inbox (App\Support\SignupGuard). */
class AllowedSignupEmail implements ValidationRule
{
    public function __construct(protected ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // The email rule reports it
        }

        if ($problem = SignupGuard::emailProblem($value)) {
            $fail($problem);
        } elseif (SignupGuard::aliasTaken($value, $this->ignoreUserId)) {
            $fail(__('هذا البريد (أو نسخة منه بنقاط أو +) مسجّل في حساب آخر. سجّل الدخول بذلك الحساب.'));
        }
    }
}
