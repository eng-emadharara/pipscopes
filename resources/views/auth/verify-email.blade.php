<x-layouts.app :title="__('تأكيد البريد الإلكتروني')" :hide-flash="true">
    <div class="mx-auto max-w-md px-4 py-14">
        <div class="mb-6 grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">✉️</div>
        <h1 class="mb-2 text-2xl font-bold">{{ __('تأكيد البريد الإلكتروني') }}</h1>
        <p class="mb-6 text-sm leading-relaxed text-gray-600">
            {{ __('أدخل الرمز المكوّن من 6 أرقام الذي أرسلناه إلى') }}
            <strong dir="ltr" class="text-ink">{{ auth()->user()->email }}</strong>.
            {{ __('إذا لم تجده، تحقق من مجلد الرسائل غير المرغوب فيها (Spam).') }}
        </p>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50 p-3 text-sm text-brand-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ session('error') }}</div>
        @endif

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

        <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center text-xs text-gray-500">
            @csrf
            {{ __('ليس بريدك؟') }}
            <button class="underline hover:text-ink">{{ __('سجّل الخروج وأنشئ حساباً جديداً') }}</button>
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
