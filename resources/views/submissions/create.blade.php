<x-layouts.app :title="__('أرسل بيانات مقدم خدمة')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('أضف مقدم خدمة إلى المنصة') }}</h1>
        <p class="mt-2 text-gray-600">{{ __('سواء كنت صاحب قناة توصيات أو خدمة نسخ صفقات، أو مستخدماً تريد إضافة مزود لتقييمه، أرسل البيانات وسيراجعها فريقنا قبل النشر.') }}</p>

        <div class="mt-8">
            @include('submissions._form')
        </div>

        @if ($telegramContact = \App\Support\Site::telegramContact())
            <p class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600">
                {{ __('صاحب القناة وعندك سؤال قبل الإضافة؟') }}
                <a href="{{ $telegramContact }}" target="_blank" rel="noopener" data-track="telegram_contact" data-track-params='{"location":"submit"}'
                   class="inline-flex items-center gap-1 font-semibold text-brand-700 hover:underline">
                    <x-ui-icon name="telegram" class="h-4 w-4" /> {{ __('راسلنا على تيليجرام') }}
                </a>
            </p>
        @endif
    </div>
</x-layouts.app>
