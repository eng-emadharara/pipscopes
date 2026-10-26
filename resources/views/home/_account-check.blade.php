@php($account ??= '')
@php($result ??= null)
{{-- "Is this account real?" — resources/js/account-check.js shows the result in place --}}
<div class="card overflow-hidden" id="account-check">
    <div class="flex flex-col gap-5 p-6 md:flex-row md:items-center md:p-8">
        <div class="md:w-2/5">
            <h2 class="flex items-center gap-2 text-xl font-bold">
                <x-ui-icon name="shield" class="h-6 w-6 text-brand-600" />
                {{ __('هل هذا الحساب حقيقي؟') }}
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('تواصل معك شخص يدّعي أنه من قناة توصيات أو خدمة نسخ صفقات؟ الصق رابط حسابه أو معرّفه وتحقق قبل أن ترسل أي أموال.') }}</p>
        </div>

        <form action="{{ route('account-check') }}" method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row" data-account-check>
            <input type="text" name="account" value="{{ $account }}" required maxlength="255" dir="ltr"
                   class="input min-w-0 flex-1 text-start" placeholder="@gold_signals_support · t.me/… · +962…"
                   aria-label="{{ __('رابط الحساب أو اسم المستخدم') }}" autocomplete="off">
            <button type="submit" class="btn-primary shrink-0">{{ __('تحقق') }}</button>
        </form>
    </div>

    <div data-account-check-result aria-live="polite">
        @if ($result)
            @include('home._account-check-result')
        @endif
    </div>
</div>
