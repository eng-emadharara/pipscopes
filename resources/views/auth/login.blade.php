<x-layouts.app :title="__('تسجيل الدخول')" :hide-flash="true">
    <div class="mx-auto max-w-md px-4 py-14">
        <h1 class="mb-2 text-2xl font-bold">{{ __('تسجيل الدخول') }}</h1>
        <p class="mb-6 text-sm text-gray-600">{{ __('سجّل دخولك لكتابة التقييمات.') }}</p>
        <div class="card p-6">
            @if (session('error'))
                <div class="mb-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                    <x-ui-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" /> {{ session('error') }}
                </div>
            @endif

            <x-social-login />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">{{ __('البريد الإلكتروني') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" dir="ltr" autocomplete="email" required>
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password">{{ __('كلمة المرور') }}</label>
                    <input id="password" type="password" name="password" class="input" dir="ltr" autocomplete="current-password" required>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" class="accent-brand-500"> {{ __('تذكرني') }}
                </label>
                <button class="btn-primary w-full">{{ __('دخول') }}</button>
                <p class="text-center text-sm text-gray-600">{{ __('ليس لديك حساب؟') }} <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">{{ __('أنشئ حساباً') }}</a></p>
            </form>
        </div>
    </div>
</x-layouts.app>
