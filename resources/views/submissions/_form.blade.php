{{-- Shared by /submit and the pre-launch page. $categories and $markets come from a view composer (AppServiceProvider).
     Only the link, markets and categories are asked: the rest is read from the channel's public Telegram page.
     resources/js/submission-form.js checks the link as it's typed and shows the channel it found. --}}
@php
    $jsText = [
        'checking' => __('جارٍ التحقق من القناة…'),
        'open' => __('افتح صفحتها وقيّمها'),
        'found' => __('وجدنا القناة. اختر الأسواق وما تقدمه ثم أرسلها.'),
        'verified' => __('موثّقة من تيليجرام'),
    ];
@endphp
<script>window.submissionText = @json($jsText);</script>
<form id="add-provider" method="POST" action="{{ route('submissions.store') }}" class="scroll-mt-6 space-y-5" data-submission-form data-check-url="{{ route('submissions.check') }}">
    @csrf
    {{-- Honeypot --}}
    <input type="text" name="website_confirm" class="hidden" tabindex="-1" autocomplete="off">

    <section class="card space-y-5 p-5 md:p-6">
        <div>
            <label class="label" for="telegram_url">{{ __('رابط قناة التيليجرام') }} <span class="text-red-500">*</span></label>
            <div class="relative" dir="ltr">
                <x-ui-icon name="telegram" class="pointer-events-none absolute start-3 top-1/2 h-5 w-5 -translate-y-1/2 text-sky-500" />
                <input id="telegram_url" name="telegram_url" value="{{ old('telegram_url') }}" class="input ps-10 text-start" dir="ltr"
                       inputmode="url" autocomplete="off" placeholder="t.me/channel  ·  @channel" required data-telegram-input>
            </div>
            <p class="mt-1.5 text-xs text-gray-500" data-telegram-status>{{ __('نقرأ اسم القناة وصورتها ووصفها وعدد مشتركيها من تيليجرام مباشرة. القنوات العامة فقط.') }}</p>
            @error('telegram_url') <p class="error">{{ $message }}</p> @enderror

            {{-- Filled by the script with the channel found on Telegram --}}
            <div class="mt-3 hidden items-center gap-3 rounded-xl border border-sky-100 bg-sky-50/60 p-3" data-channel-preview>
                <img alt="" class="hidden h-12 w-12 shrink-0 rounded-full object-cover" data-channel-photo>
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-sky-500 text-white" data-channel-initial>
                    <x-ui-icon name="telegram" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <p class="truncate font-bold" dir="auto" data-channel-title></p>
                    <p class="text-xs text-gray-600" data-channel-meta></p>
                </div>
            </div>
        </div>

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
    </section>

    <button class="btn-primary w-full py-3 text-base sm:w-auto sm:px-10">{{ __('إرسال القناة') }}</button>
</form>
