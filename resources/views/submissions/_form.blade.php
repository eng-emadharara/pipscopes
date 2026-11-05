{{-- Shared by /submit and the pre-launch page. Only the link is asked: the rest is read from the channel's
     public Telegram page, and the admin picks the categories and markets when approving it.
     resources/js/submission-form.js checks the link as it's typed and shows the channel it found. --}}
@php
    $jsText = [
        'checking' => __('جارٍ التحقق من القناة…'),
        'open' => __('افتح صفحتها وقيّمها'),
        'found' => __('وجدنا القناة. اضغط «إرسال القناة».'),
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
    </section>

    <button class="btn-primary w-full py-3 text-base sm:w-auto sm:px-10">{{ __('إرسال القناة') }}</button>

    {{-- Or leave it to us --}}
    @if ($telegramContact = \App\Support\Site::telegramContact())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-sky-100 bg-sky-50/60 p-4 text-center sm:flex-row sm:justify-between sm:text-start" data-submit-contact>
            <p class="text-sm text-gray-700">{{ __('تفضّل أن نضيفها نحن؟ راسلنا برابط القناة وسنضيفها لك.') }}</p>
            <a href="{{ $telegramContact }}" target="_blank" rel="noopener" data-track="telegram_contact" data-track-params='{"location":"submit"}'
               class="btn w-full shrink-0 whitespace-nowrap bg-[#229ED9] text-white hover:bg-[#1a8bc2] sm:w-auto">
                <x-ui-icon name="telegram" class="h-4 w-4" /> {{ __('راسلنا على تيليجرام') }}
            </a>
        </div>
    @endif
</form>
