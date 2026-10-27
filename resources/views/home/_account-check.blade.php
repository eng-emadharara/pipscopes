@php($account ??= '')
@php($result ??= null)
{{-- "Is this account real?" — resources/js/account-check.js shows the result in place --}}
<div class="relative overflow-hidden rounded-3xl bg-white shadow-float ring-1 ring-black/5" id="account-check">
    {{-- Brand wash behind the explanation side --}}
    <div class="pointer-events-none absolute inset-y-0 start-0 w-2/3 bg-gradient-to-r from-brand-50/80 to-transparent rtl:bg-gradient-to-l" aria-hidden="true"></div>

    <div class="relative grid gap-6 p-6 md:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] md:items-center md:gap-10 md:p-10">
        <div>
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-500 text-white shadow-lg shadow-brand-500/25">
                <x-ui-icon name="shield" class="h-6 w-6" />
            </span>
            <h2 class="mt-4 text-2xl font-bold tracking-tight">{{ __('هل هذا الحساب حقيقي؟') }}</h2>
            <p class="mt-2 max-w-md text-pretty text-sm leading-relaxed text-gray-600">{{ __('تواصل معك شخص يدّعي أنه من قناة توصيات أو خدمة نسخ صفقات؟ الصق رابط حسابه أو معرّفه وتحقق قبل أن ترسل أي أموال.') }}</p>
        </div>

        <div>
            <form action="{{ route('account-check') }}" method="GET" class="flex flex-col gap-2 rounded-2xl bg-sand p-2 sm:flex-row" data-account-check>
                <input type="text" name="account" value="{{ $account }}" required maxlength="255" dir="ltr"
                       class="input min-w-0 flex-1 border-transparent py-3 text-start shadow-sm" placeholder="@gold_signals_support · t.me/… · +962…"
                       aria-label="{{ __('رابط الحساب أو اسم المستخدم') }}" autocomplete="off">
                <button type="submit" class="btn-dark shrink-0 rounded-xl px-6 py-3 active:scale-[.98]">
                    <x-ui-icon name="search" class="h-4 w-4" />
                    {{ __('تحقق') }}
                </button>
            </form>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 px-1 text-xs text-gray-500">
                @foreach ([__('رابط تيليجرام'), __('معرّف يبدأ بـ @'), __('رقم هاتف')] as $hint)
                    <li class="flex items-center gap-1.5">
                        <x-ui-icon name="check" class="h-3.5 w-3.5 text-brand-500" />
                        {{ $hint }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div data-account-check-result aria-live="polite" class="relative">
        @if ($result)
            @include('home._account-check-result')
        @endif
    </div>
</div>
