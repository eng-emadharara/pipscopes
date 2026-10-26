<x-layouts.app :title="__('دخول أصحاب القنوات')">
    <div class="mx-auto max-w-md px-4 py-14">
        <div class="mb-6 grid h-14 w-14 place-items-center rounded-2xl bg-sky-50 text-2xl">📣</div>
        <h1 class="mb-2 text-2xl font-bold">{{ __('دخول أصحاب القنوات') }}</h1>
        <p class="mb-6 text-sm leading-relaxed text-gray-600">{{ __('ادخل بالحساب الذي أرسلته لك إدارة المنصة لترد على تقييمات قناتك وتعدّل بياناتها من صفحة قناتك مباشرة.') }}</p>

        <form method="POST" action="{{ route('owner.login') }}" class="card space-y-4 p-6">
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
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            {{ __('ليس لديك حساب لقناتك؟') }}
            <a href="{{ route('contact') }}" class="font-semibold text-brand-600 hover:underline">{{ __('تواصل معنا') }}</a>
        </p>
    </div>
</x-layouts.app>
