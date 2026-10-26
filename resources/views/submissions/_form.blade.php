{{-- Shared by /submit and the pre-launch page. $categories and $markets come from a view composer (AppServiceProvider).
     resources/js/submission-form.js checks the Telegram link as it's typed and remembers the submitter's details. --}}
@php
    $otherLinks = array_diff(array_keys(config('trust.socials')), ['telegram_url']);
    // Keep optional sections open when they hold old input or errors
    $detailsOpen = collect(['service_type', 'description', 'pricing', ...$otherLinks])->contains(fn ($f) => filled(old($f)) || $errors->has($f));
    $member = auth()->user();
    $jsText = [
        'checking' => __('جارٍ التحقق من القناة…'),
        'exists' => __('هذه القناة موجودة على المنصة باسم «:name».'),
        'open' => __('افتح صفحتها وقيّمها'),
        'pending' => __('أرسل أحدهم هذه القناة مسبقاً وهي قيد المراجعة الآن.'),
        'new' => __('القناة غير موجودة لدينا بعد، أكمل البيانات.'),
        'invalid' => __('أدخل رابط قناة تيليجرام صحيحاً، مثل t.me/channel أو @channel.'),
    ];
@endphp
<script>window.submissionText = @json($jsText);</script>
<form id="add-provider" method="POST" action="{{ route('submissions.store') }}" class="scroll-mt-6 space-y-5" data-submission-form data-check-url="{{ route('submissions.check') }}">
    @csrf
    {{-- Honeypot --}}
    <input type="text" name="website_confirm" class="hidden" tabindex="-1" autocomplete="off">

    {{-- 1. The channel --}}
    <section class="card space-y-5 p-5 md:p-6">
        <h2 class="flex items-center gap-2 font-bold">
            <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-500 text-sm text-white">1</span>
            {{ __('القناة') }}
        </h2>

        <div>
            <label class="label" for="telegram_url">{{ __('رابط قناة التيليجرام') }} <span class="text-red-500">*</span></label>
            <div class="relative" dir="ltr">
                <x-ui-icon name="telegram" class="pointer-events-none absolute start-3 top-1/2 h-5 w-5 -translate-y-1/2 text-sky-500" />
                <input id="telegram_url" name="telegram_url" value="{{ old('telegram_url') }}" class="input ps-10 text-start" dir="ltr"
                       inputmode="url" autocomplete="off" placeholder="t.me/channel  ·  @channel" required data-telegram-input>
            </div>
            <p class="mt-1.5 text-xs text-gray-500" data-telegram-status>{{ __('الصق الرابط وسنتحقق إن كانت القناة موجودة لدينا ونقترح اسمها.') }}</p>
            @error('telegram_url') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="name">{{ __('اسم القناة') }} <span class="text-red-500">*</span></label>
            <input id="name" name="name" dir="auto" value="{{ old('name') }}" class="input" required maxlength="150" data-name-input>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="label">{{ __('ماذا تقدم؟') }} <span class="text-red-500">*</span> <span class="font-normal text-gray-500">({{ __('اختر واحداً أو أكثر') }})</span></legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <label class="chip chip-pro">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="sr-only" @checked(in_array($category->id, old('category_ids', [])))>
                        {{ $category->icon }} {{ $category->tr('name') }}
                    </label>
                @endforeach
            </div>
            @error('category_ids') <p class="error">{{ $message }}</p> @enderror
            @error('category_ids.*') <p class="error">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset>
            <legend class="label">{{ __('في أي سوق؟') }} <span class="text-red-500">*</span> <span class="font-normal text-gray-500">({{ __('اختر واحداً أو أكثر') }})</span></legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($markets as $market)
                    <label class="chip chip-pro">
                        <input type="checkbox" name="market_ids[]" value="{{ $market->id }}" class="sr-only" @checked(in_array($market->id, old('market_ids', [])))>
                        {{ $market->icon }} {{ $market->tr('name') }}
                    </label>
                @endforeach
            </div>
            @error('market_ids') <p class="error">{{ $message }}</p> @enderror
            @error('market_ids.*') <p class="error">{{ $message }}</p> @enderror
        </fieldset>

        <details class="group rounded-xl border border-gray-200" @if ($detailsOpen) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <span>{{ __('تفاصيل إضافية') }} <span class="font-normal text-gray-500">({{ __('اختياري: السعر، نبذة، روابط أخرى') }})</span></span>
                <span class="text-gray-400 transition group-open:rotate-180">▾</span>
            </summary>
            <div class="space-y-4 border-t border-gray-100 p-4">
                <fieldset>
                    <legend class="label">{{ __('التسعير') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (__('trust.pricing_options') as $key => $label)
                            <label class="chip chip-pro">
                                <input type="radio" name="pricing" value="{{ $key }}" class="sr-only" @checked(old('pricing') === $key)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label class="label" for="service_type">{{ __('وصف قصير للخدمة') }}</label>
                    <input id="service_type" name="service_type" dir="auto" value="{{ old('service_type') }}" class="input" maxlength="150" placeholder="{{ __('مثال: توصيات ذهب وفوركس') }}">
                    @error('service_type') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="description">{{ __('نبذة عن القناة') }}</label>
                    <textarea id="description" name="description" dir="auto" rows="3" class="input">{{ old('description') }}</textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($otherLinks as $field)
                        <div>
                            <label class="label" for="{{ $field }}">{{ __("trust.socials.$field") }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="input text-start" dir="ltr" inputmode="url" placeholder="{{ $field === 'website_url' ? 'example.com' : 'link / @username' }}">
                            @error($field) <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    </section>

    {{-- 2. Who is sending it --}}
    <section class="card space-y-4 p-5 md:p-6">
        <h2 class="flex items-center gap-2 font-bold">
            <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-500 text-sm text-white">2</span>
            {{ __('بياناتك') }}
            <span class="text-xs font-normal text-gray-500">{{ __('(لا تظهر للعامة)') }}</span>
        </h2>
        <div class="flex flex-wrap gap-2">
            @foreach (__('trust.relations') as $key => $label)
                <label class="chip chip-pro">
                    <input type="radio" name="relation" value="{{ $key }}" class="sr-only" @checked(old('relation', 'owner') === $key)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="label" for="submitter_name">{{ __('اسمك') }} <span class="text-red-500">*</span></label>
                <input id="submitter_name" name="submitter_name" value="{{ old('submitter_name', $member?->name) }}" class="input" required maxlength="100" autocomplete="name" data-remember>
                @error('submitter_name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="submitter_contact">{{ __('وسيلة تواصل (بريد أو معرف تيليجرام)') }} <span class="text-red-500">*</span></label>
                <input id="submitter_contact" name="submitter_contact" value="{{ old('submitter_contact', $member?->email) }}" class="input text-start" dir="ltr" placeholder="@username / email" required maxlength="150" data-remember>
                @error('submitter_contact') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <button class="btn-primary w-full py-3 text-base sm:w-auto sm:px-10">{{ __('إرسال الطلب') }}</button>
</form>
