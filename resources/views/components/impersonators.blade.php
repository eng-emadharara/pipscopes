@props(['provider', 'impersonators'])
@php
    $reportErrors = $errors->getBag('impersonator');
    // Keep the report form open after a failed or successful submit
    $formOpen = $reportErrors->any() || session('impersonator_success');
@endphp
<div class="card scroll-mt-6 overflow-hidden" id="impersonators">
    <div class="p-5 {{ $impersonators->isNotEmpty() ? 'bg-red-50' : '' }}">
        <h2 class="mb-1 flex items-center gap-2 font-bold {{ $impersonators->isNotEmpty() ? 'text-red-800' : '' }}">
            <x-ui-icon name="alert" class="h-5 w-5 {{ $impersonators->isNotEmpty() ? 'text-red-600' : 'text-amber-500' }}" />
            {{ __('حسابات منتحلة معروفة') }}
        </h2>

        @if ($impersonators->isEmpty())
            <p class="text-xs leading-relaxed text-gray-600">{{ __('لا توجد حسابات منتحلة مؤكدة حتى الآن. لا ترسل أموالاً لأي شخص يتواصل معك بشكل خاص مدعياً أنه مقدم الخدمة.') }}</p>
        @else
            <p class="mb-3 text-xs leading-relaxed text-red-800">{{ __('هذه الحسابات ليست تابعة لمقدم الخدمة وتنتحل اسمه. لا تتواصل معها ولا ترسل لها أموالاً.') }}</p>
            <ul class="space-y-2">
                @foreach ($impersonators as $fake)
                    {{-- Deliberately not a link: we never send traffic to a scam account --}}
                    <li class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <x-ui-icon :name="$fake->icon()" class="h-4 w-4 shrink-0 text-red-600" />
                            <span class="font-mono font-semibold text-red-700 line-through decoration-red-300" dir="ltr">{{ $fake->handle }}</span>
                            <span class="ms-auto shrink-0 text-xs text-gray-500">{{ $fake->platformLabel() }}</span>
                        </div>
                        @if ($fake->public_note)
                            <p class="mt-1 text-xs text-gray-600">{{ $fake->public_note }}</p>
                        @endif
                        @if ($fake->reports_count)
                            <p class="mt-1 text-xs text-gray-400">{{ trans_choice('أبلغ عنه :count شخص', $fake->reports_count) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <details class="border-t border-gray-100" @if ($formOpen) open @endif>
        <summary class="cursor-pointer list-none px-5 py-3 text-sm font-semibold text-red-700 hover:bg-red-50">
            {{ __('تواصل معك حساب ينتحل هذه القناة؟ بلّغ عنه') }}
        </summary>
        <div class="px-5 pb-5">
            @if (session('impersonator_success'))
                <div class="mb-3 rounded-lg border border-brand-100 bg-brand-50 p-3 text-sm text-brand-700">{{ session('impersonator_success') }}</div>
            @endif

            <form method="POST" action="{{ route('impersonators.report', $provider) }}" class="space-y-3 text-sm">
                @csrf
                <div>
                    <label class="label" for="imp-platform">{{ __('المنصة') }}</label>
                    <select id="imp-platform" name="platform" class="input" required>
                        @foreach (__('trust.account_platforms') as $key => $label)
                            <option value="{{ $key }}" @selected(old('platform', 'telegram') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="imp-account">{{ __('رابط الحساب أو اسم المستخدم') }}</label>
                    <input id="imp-account" name="account" value="{{ old('account') }}" class="input" dir="ltr" maxlength="255" placeholder="@fake_channel / https://t.me/…" required>
                    @if ($reportErrors->has('account'))
                        <p class="error">{{ $reportErrors->first('account') }}</p>
                    @endif
                </div>
                <div>
                    <label class="label" for="imp-details">{{ __('كيف تواصل معك؟ (اختياري)') }}</label>
                    <textarea id="imp-details" name="details" rows="2" dir="auto" maxlength="2000" class="input" placeholder="{{ __('مثال: راسلني على الخاص وطلب تحويل اشتراك VIP') }}">{{ old('details') }}</textarea>
                </div>
                <div>
                    <label class="label" for="imp-contact">{{ __('وسيلة للتواصل معك (اختياري)') }}</label>
                    <input id="imp-contact" name="contact" value="{{ old('contact') }}" class="input" dir="ltr" maxlength="150" placeholder="@username / email">
                </div>
                <button class="btn bg-red-600 px-4 py-2 text-white hover:bg-red-700">{{ __('إرسال البلاغ') }}</button>
                <p class="text-xs text-gray-500">{{ __('نراجع كل بلاغ قبل نشره، ولا نظهر بياناتك للعامة.') }}</p>
            </form>
        </div>
    </details>
</div>
