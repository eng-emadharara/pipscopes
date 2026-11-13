{{-- A member's scam report: what happened, who it was, the proofs (private), and what we may do with it --}}
@php
    $types = \App\Support\ScamHelp::types();
    $payments = \App\Support\ScamHelp::payments();
    $selectedType = old('scam_type', $type);
    $selectedPayment = old('payment_method', $payment);
@endphp
<x-layouts.app :title="__('بلّغ عن نصب')">
    <section class="bg-ink pb-16 pt-10 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <h1 class="text-2xl font-bold md:text-4xl">{{ __('بلّغ عن نصب') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-gray-300 md:text-base">
                {{ __('بلاغك يُجمع مع بلاغات الآخرين عن نفس الجهة في ملف واحد، ونحاول التحرك به. يأخذ دقيقتين.') }}
            </p>
        </div>
    </section>

    <div class="mx-auto -mt-10 grid max-w-5xl gap-5 px-4 pb-12 lg:grid-cols-[1fr_300px] lg:items-start">
        <form method="POST" action="{{ route('scam-reports.store') }}" enctype="multipart/form-data" class="card space-y-7 p-5 shadow-sm md:p-8" data-scam-report-form>
            @csrf

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ __('راجع الحقول المظللة بالأحمر.') }}</div>
            @endif

            <fieldset>
                <legend class="label text-base">{{ __('ماذا حدث؟') }} <span class="text-red-500">*</span></legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($types as $key => [$emoji, $label])
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm hover:border-brand-500 has-checked:border-brand-500 has-checked:bg-brand-50">
                            <input type="radio" name="scam_type" value="{{ $key }}" class="accent-brand-500" @checked($selectedType === $key) required>
                            <span><span aria-hidden="true">{{ $emoji }}</span> {{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('scam_type') <p class="error">{{ $message }}</p> @enderror
            </fieldset>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label" for="payment_method">{{ __('كيف دفعت؟') }} <span class="text-red-500">*</span></label>
                    <select id="payment_method" name="payment_method" class="input" required>
                        <option value="">{{ __('— اختر —') }}</option>
                        @foreach ($payments as $key => $label)
                            <option value="{{ $key }}" @selected($selectedPayment === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="paid_at">{{ __('متى دفعت؟') }}</label>
                    <input id="paid_at" type="date" name="paid_at" value="{{ old('paid_at', $paidAt?->toDateString()) }}" max="{{ now()->toDateString() }}" class="input" dir="ltr">
                    @error('paid_at') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-[1fr_140px]">
                <div>
                    <label class="label" for="amount">{{ __('كم خسرت؟') }}</label>
                    <input id="amount" type="number" name="amount" min="0" step="any" value="{{ old('amount') }}" class="input" dir="ltr" inputmode="decimal">
                    @error('amount') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="currency">{{ __('العملة') }}</label>
                    <select id="currency" name="currency" class="input" dir="ltr">
                        @foreach (array_keys(\App\Support\ScamHelp::CURRENCIES) as $code)
                            <option value="{{ $code }}" @selected(old('currency', 'USD') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                    @error('currency') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <fieldset class="space-y-4 rounded-2xl bg-gray-50 p-4">
                <legend class="label text-base">{{ __('من الجهة التي نصبت عليك؟') }}</legend>
                <p class="-mt-1 text-xs text-gray-600">{{ __('املأ واحداً على الأقل. بها نجمع بلاغك مع البلاغات الأخرى عن نفس الجهة.') }}</p>
                <div>
                    <label class="label" for="account">{{ __('رابط القناة أو الحساب') }}</label>
                    <input id="account" name="account" value="{{ old('account', $account) }}" class="input" dir="ltr" maxlength="255" placeholder="t.me/… أو ‎@name">
                    @error('account') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="label" for="wallet">{{ __('عنوان المحفظة التي حوّلت إليها') }}</label>
                        <input id="wallet" name="wallet" value="{{ old('wallet') }}" class="input font-mono text-xs" dir="ltr" maxlength="120" placeholder="TX… / 0x…">
                        @error('wallet') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="broker">{{ __('اسم الوسيط (إن وُجد)') }}</label>
                        <input id="broker" name="broker" value="{{ old('broker') }}" class="input" dir="auto" maxlength="120">
                        @error('broker') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <div>
                <label class="label" for="story">{{ __('اشرح ما حدث') }} <span class="text-red-500">*</span></label>
                <textarea id="story" name="story" rows="6" class="input" dir="auto" minlength="30" maxlength="5000" required
                          placeholder="{{ __('كيف تعرفت عليهم، ماذا وعدوك، كم دفعت ومتى، وماذا حدث بعدها.') }}">{{ old('story') }}</textarea>
                @error('story') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="proofs">{{ __('الإثباتات (صور)') }}</label>
                <input id="proofs" type="file" name="proofs[]" accept="image/jpeg,image/png,image/webp" multiple
                       class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:font-semibold file:text-brand-700">
                <p class="mt-1 text-xs leading-relaxed text-gray-600">
                    {{ __('حتى :n صور: المحادثات، إيصال الدفع، صفحة الحساب. لا تُنشر أبداً، ولا يراها إلا فريق المراجعة.', ['n' => \App\Models\ScamReport::MAX_PROOFS]) }}
                    <strong class="text-amber-700">{{ __('غطِّ رقم البطاقة وأي كلمة مرور قبل الرفع.') }}</strong>
                </p>
                @error('proofs') <p class="error">{{ $message }}</p> @enderror
                @error('proofs.*') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="contact">{{ __('وسيلة تواصل إضافية (اختياري)') }}</label>
                <input id="contact" name="contact" value="{{ old('contact') }}" class="input" dir="auto" maxlength="255" placeholder="{{ __('رقم واتساب أو حساب تيليجرام') }}">
            </div>

            <div class="space-y-3 border-t border-gray-100 pt-5 text-sm">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="consent_true" value="1" class="mt-1 accent-brand-500" @checked(old('consent_true')) required>
                    <span>{{ __('المعلومات صحيحة حسب علمي، وأخفيت رقم البطاقة والبيانات الحساسة من الصور.') }} <span class="text-red-500">*</span></span>
                </label>
                @error('consent_true') <p class="error">{{ $message }}</p> @enderror
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="consent_share" value="1" class="mt-1 accent-brand-500" @checked(old('consent_share', true))>
                    <span>{{ __('أوافق على مشاركة بلاغي بدون اسمي مع الجهات المختصة ضمن ملف جماعي (تيليجرام، الجهات الرقابية، منصات التحويل).') }}</span>
                </label>
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="consent_contact" value="1" class="mt-1 accent-brand-500" @checked(old('consent_contact'))>
                    <span>{{ __('تواصلوا معي إذا صار هناك تحرك جماعي، مثل محامٍ أو شكوى رسمية.') }}</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full">{{ __('أرسل البلاغ') }}</button>
        </form>

        <aside class="space-y-4 lg:sticky lg:top-4">
            <div class="card p-5">
                <h2 class="font-bold">{{ __('ماذا يحدث بعد الإرسال؟') }}</h2>
                <ol class="mt-3 space-y-3 text-sm text-gray-700">
                    <li>1. {{ __('يراجع فريقنا البلاغ والإثباتات.') }}</li>
                    <li>2. {{ __('إذا تأكدنا منه يُضاف لملف الجهة، وتعرف كم بلاغاً فيه.') }}</li>
                    <li>3. {{ __('عندما يكتمل الملف نحاول التحرك به، ونخبرك بكل خطوة.') }}</li>
                    <li>4. {{ __('تحصل على نسخة من الملف الجماعي لشكواك.') }}</li>
                </ol>
            </div>
            <p class="rounded-xl bg-gray-100 p-4 text-xs leading-relaxed text-gray-600">
                {{ __('لا نعدك باسترجاع أموالك، ولا نطلب منك أي مبلغ في أي مرحلة.') }}
                <a href="{{ route('scam-help') }}" class="font-semibold underline">{{ __('ماذا أفعل الآن؟') }}</a>
            </p>
        </aside>
    </div>
</x-layouts.app>
