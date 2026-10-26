<x-layouts.app :title="__('تسجيل الدخول')">
    <div class="mx-auto max-w-md px-4 py-14">
        <h1 class="mb-2 text-2xl font-bold">{{ __('تسجيل الدخول') }}</h1>
        <p class="mb-6 text-sm text-gray-600">{{ __('سجّل دخولك لكتابة التقييمات.') }}</p>
        <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-6">
            @csrf
            <div>
                <label class="label" for="email">{{ __('البريد الإلكتروني') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" dir="ltr" required autofocus>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password">{{ __('كلمة المرور') }}</label>
                <input id="password" type="password" name="password" class="input" dir="ltr" required>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="accent-brand-500"> {{ __('تذكرني') }}
            </label>
            <button class="btn-primary w-full">{{ __('دخول') }}</button>
            <p class="text-center text-sm text-gray-600">{{ __('ليس لديك حساب؟') }} <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">{{ __('أنشئ حساباً') }}</a></p>
        </form>
    </div>
</x-layouts.app>
