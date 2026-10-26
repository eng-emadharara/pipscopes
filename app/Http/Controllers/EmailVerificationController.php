<?php

namespace App\Http\Controllers;

use App\Support\EmailVerification;
use Illuminate\Http\Request;

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

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        if ($wait = EmailVerification::resendWait($user)) {
            return back()->withErrors(['code' => __('انتظر :seconds ثانية قبل طلب رمز جديد.', ['seconds' => $wait])]);
        }

        return EmailVerification::send($user)
            ? back()->with('success', __('أرسلنا رمزاً جديداً إلى بريدك الإلكتروني.'))
            : back()->withErrors(['code' => __('تعذّر إرسال البريد حالياً. حاول مرة أخرى بعد قليل.')]);
    }
}
