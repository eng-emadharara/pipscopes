<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\AllowedSignupEmail;
use App\Support\EmailVerification;
use App\Support\SignupGuard;
use App\Support\SocialLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Accounts created through Google/Facebook/Telegram have no password: point to the right button
            $providers = User::where('email', Str::lower(trim($credentials['email'])))->whereNull('password')->first()
                ?->socialAccounts()->pluck('provider')->map(fn ($p) => SocialLogin::LABELS[$p] ?? $p)->implode(' / ');

            return back()->withErrors(['email' => $providers
                ? __('هذا الحساب يسجّل الدخول عبر :provider. استخدم الزر بالأعلى.', ['provider' => $providers])
                : __('البريد الإلكتروني أو كلمة المرور غير صحيحة.')])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', new AllowedSignupEmail],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => __('الاسم'),
            'email' => __('البريد الإلكتروني'),
            'password' => __('كلمة المرور'),
        ]);

        if (SignupGuard::ipLimitReached($request->ip())) {
            return back()->withErrors(['email' => __('تم إنشاء عدة حسابات من هذا الجهاز اليوم. حاول غداً أو سجّل الدخول بحسابك الحالي.')])->onlyInput('name', 'email');
        }

        try {
            $user = (new User($data))->forceFill(['signup_ip' => $request->ip()]);
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request (e.g. double submit) created the same email after validation passed
            return back()->withErrors(['email' => __('هذا البريد مسجّل مسبقاً، يمكنك تسجيل الدخول.')])->onlyInput('name', 'email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Where they were heading stays in url.intended and is used after the code is confirmed
        $sent = EmailVerification::send($user);

        return redirect()->route('verification.notice')->with($sent ? 'success' : 'error', $sent
            ? __('أهلاً بك! أرسلنا رمز تأكيد مكوّناً من 6 أرقام إلى :email.', ['email' => $user->email])
            : __('تم إنشاء حسابك، لكن تعذّر إرسال رمز التأكيد. اضغط «إرسال رمز جديد» بعد قليل.'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
