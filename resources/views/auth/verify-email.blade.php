<x-layouts.app :title="__('تأكيد البريد الإلكتروني')" :hide-flash="true">
    @php $user = auth()->user(); @endphp
    <div class="mx-auto max-w-md px-4 py-14">
        <div class="mb-6 grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">✉️</div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50 p-3 text-sm text-brand-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ session('error') }}</div>
        @endif

        @if (! $user->email)
            {{-- Signed in with Telegram (or Facebook without an email): reviews still need a confirmed address --}}
            <h1 class="mb-2 text-2xl font-bold">{{ __('أضف بريدك الإلكتروني') }}</h1>
            <p class="mb-6 text-sm leading-relaxed text-gray-600">
                {{ __('نطلب بريداً مؤكَّداً من كل من يكتب تقييماً، حتى تبقى التقييمات حقيقية. لن نعرضه لأحد.') }}
            </p>
            <form method="POST" action="{{ route('verification.email') }}" class="card space-y-4 p-6">
                @csrf
                <div>
                    <label class="label" for="email">{{ __('البريد الإلكتروني') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" dir="ltr" autocomplete="email" required autofocus>
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <button class="btn-primary w-full">{{ __('إرسال رمز التأكيد') }}</button>
            </form>
        @else
            <h1 class="mb-2 text-2xl font-bold">{{ __('تأكيد البريد الإلكتروني') }}</h1>
            <p class="mb-6 text-sm leading-relaxed text-gray-600">
                {{ __('أدخل الرمز المكوّن من 6 أرقام الذي أرسلناه إلى') }}
                <strong dir="ltr" class="text-ink">{{ $user->email }}</strong>.
                {{ __('إذا لم تجده، تحقق من مجلد الرسائل غير المرغوب فيها (Spam).') }}
            </p>

            <form method="POST" action="{{ route('verification.verify') }}" class="card space-y-4 p-6">
                @csrf
                <div>
                    <label class="label" for="code">{{ __('رمز التأكيد') }}</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                           dir="ltr" class="input text-center text-2xl font-bold tracking-[0.5em]" placeholder="------" required autofocus>
                    @error('code') <p class="error">{{ $message }}</p> @enderror
                </div>
                <button class="btn-primary w-full">{{ __('تأكيد') }}</button>
            </form>

            <form method="POST" action="{{ route('verification.resend') }}" class="mt-4 text-center text-sm">
                @csrf
                <span class="text-gray-500">{{ __('لم يصلك الرمز؟') }}</span>
                <button class="font-semibold text-brand-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                        data-resend-wait="{{ $resendWait }}" @disabled($resendWait > 0)>
                    {{ __('إرسال رمز جديد') }}<span data-resend-countdown>{{ $resendWait ? " ($resendWait)" : '' }}</span>
                </button>
            </form>

            <details class="group mt-6 text-sm" @if ($errors->has('email')) open @endif>
                <summary class="cursor-pointer list-none text-center text-gray-500 hover:text-ink">
                    {{ __('البريد غير صحيح؟') }} <span class="font-semibold text-brand-600 group-open:hidden">{{ __('غيّره') }}</span>
                </summary>
                <form method="POST" action="{{ route('verification.email') }}" class="card mt-3 flex gap-2 p-3">
                    @csrf
                    <input type="email" name="email" value="{{ old('email') }}" class="input" dir="ltr" autocomplete="email"
                           placeholder="name@example.com" aria-label="{{ __('البريد الإلكتروني') }}" required>
                    <button class="btn-dark shrink-0">{{ __('إرسال') }}</button>
                </form>
                @error('email') <p class="error text-center">{{ $message }}</p> @enderror
            </details>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center text-xs text-gray-500">
            @csrf
            <button class="underline hover:text-ink">{{ __('تسجيل الخروج') }}</button>
        </form>
    </div>

    <script>
        // Re-enables "send a new code" when the cooldown ends
        document.querySelectorAll('[data-resend-wait]').forEach((btn) => {
            let left = Number(btn.dataset.resendWait);
            const label = btn.querySelector('[data-resend-countdown]');
            if (left <= 0) return;
            const timer = setInterval(() => {
                left -= 1;
                label.textContent = left > 0 ? ` (${left})` : '';
                if (left <= 0) { btn.disabled = false; clearInterval(timer); }
            }, 1000);
        });
    </script>
</x-layouts.app>
