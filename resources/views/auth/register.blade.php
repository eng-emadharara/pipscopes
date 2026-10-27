<x-layouts.app :title="__('حساب جديد')">
    <div class="mx-auto max-w-md px-4 py-14">
        <h1 class="mb-2 text-2xl font-bold">{{ __('إنشاء حساب') }}</h1>
        <p class="mb-6 text-sm text-gray-600">{{ __('يمكنك اختيار إخفاء اسمك عند نشر أي تقييم.') }}</p>
        <div class="card p-6">
            <x-social-login :divider="__('أو أنشئ حساباً بالبريد')" />

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="name">{{ __('الاسم') }}</label>
                    <input id="name" name="name" dir="auto" value="{{ old('name') }}" class="input" autocomplete="name" required>
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="email">{{ __('البريد الإلكتروني') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" dir="ltr" autocomplete="email" required>
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password">{{ __('كلمة المرور') }}</label>
                    <input id="password" type="password" name="password" class="input" dir="ltr" autocomplete="new-password" required>
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">{{ __('تأكيد كلمة المرور') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input" dir="ltr" autocomplete="new-password" required>
                </div>
                <button class="btn-primary w-full">{{ __('إنشاء الحساب') }}</button>
                <p class="text-center text-sm text-gray-600">{{ __('لديك حساب؟') }} <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">{{ __('سجّل دخولك') }}</a></p>
            </form>
        </div>
    </div>
</x-layouts.app>
