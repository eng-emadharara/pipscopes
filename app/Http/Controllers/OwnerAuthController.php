<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sign-in for channel owners (accounts are created by admins; there is no sign-up). */
class OwnerAuthController extends Controller
{
    public function showLogin()
    {
        if ($owner = auth('owner')->user()) {
            return redirect()->route('providers.show', $owner->provider);
        }

        return view('auth.owner-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('owner');

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('البريد الإلكتروني أو كلمة المرور غير صحيحة.')])->onlyInput('email');
        }

        if (! $guard->user()->canSignIn()) {
            $guard->logout();

            return back()->withErrors(['email' => __('هذا الحساب موقوف حالياً. تواصل مع إدارة المنصة.')])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('providers.show', $guard->user()->provider)
            ->with('success', __('أهلاً :name! يمكنك الآن الرد على التقييمات وتعديل بيانات قناتك من هذه الصفحة.', ['name' => $guard->user()->name]));
    }

    public function logout(Request $request)
    {
        $provider = auth('owner')->user()?->provider;
        Auth::guard('owner')->logout();
        $request->session()->regenerateToken();

        return $provider ? redirect()->route('providers.show', $provider) : redirect()->route('home');
    }
}
