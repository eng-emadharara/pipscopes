<?php

namespace App\Http\Controllers;

use App\Support\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailVerificationController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home'));
        }

        return view('auth.verify-email', [
            'resendWait' => EmailVerification::resendWait($request->user()),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']], [], ['code' => __('رمز التأكيد')]);

        $result = EmailVerification::verify($request->user(), $request->string('code'));

        if ($result === 'ok') {
            return redirect()->intended(route('home'))->with('success', __('تم تأكيد بريدك الإلكتروني. يمكنك الآن كتابة التقييمات.'));
        }

        $message = match ($result) {
            'expired' => __('انتهت صلاحية الرمز. اطلب رمزاً جديداً.'),
            'too_many' => __('محاولات خاطئة كثيرة. اطلب رمزاً جديداً.'),
            default => __('الرمز غير صحيح. تأكد منه وحاول مرة أخرى.'),
        };

        return back()->withErrors(['code' => $message]);
    }

    /** Sets the address to confirm: for Telegram/Facebook sign-ins that came without one, or to fix a typo. */
    public function updateEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.unique' => __('هذا البريد مستخدم في حساب آخر.'),
        ], ['email' => __('البريد الإلكتروني')]);

        if ($user->email !== $request->input('email')) {
            $user->forceFill(['email' => $request->input('email'), 'verification_code_sent_at' => null])->save();
        } elseif ($wait = EmailVerification::resendWait($user)) {
            return back()->withErrors(['email' => __('انتظر :seconds ثانية قبل طلب رمز جديد.', ['seconds' => $wait])]);
        }

        return EmailVerification::send($user)
            ? redirect()->route('verification.notice')->with('success', __('أرسلنا رمز تأكيد مكوّناً من 6 أرقام إلى :email.', ['email' => $user->email]))
            : redirect()->route('verification.notice')->with('error', __('تعذّر إرسال البريد حالياً. حاول مرة أخرى بعد قليل.'));
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        if (! $user->email) {
            return redirect()->route('verification.notice');
        }

        if ($wait = EmailVerification::resendWait($user)) {
            return back()->withErrors(['code' => __('انتظر :seconds ثانية قبل طلب رمز جديد.', ['seconds' => $wait])]);
        }

        return EmailVerification::send($user)
            ? back()->with('success', __('أرسلنا رمزاً جديداً إلى بريدك الإلكتروني.'))
            : back()->withErrors(['code' => __('تعذّر إرسال البريد حالياً. حاول مرة أخرى بعد قليل.')]);
    }
}
