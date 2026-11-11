{{-- While a channel check loads (a few seconds: the server reads the channel from Telegram); resources/js/channel-check.js shows it and moves through the steps --}}
@php
    $steps = [
        __('جاري جلب بيانات القناة من تيليجرام…'),
        __('جاري قراءة آخر المنشورات…'),
        __('جاري التحقق من الصفقات والادعاءات…'),
        __('جاري حساب التفاعل والمؤشرات…'),
        __('لحظات ويجهز التقرير…'),
    ];
@endphp
<div class="fixed inset-0 z-[60] hidden place-items-center bg-ink/80 p-4 backdrop-blur-sm" data-check-loading data-steps='@json($steps)' role="status" aria-live="polite">
    <div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-2xl">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-brand-100 border-t-brand-500" aria-hidden="true"></div>
        <p class="mt-5 min-h-12 text-base font-bold text-ink" data-check-loading-text>{{ $steps[0] }}</p>
        {{-- One dot per step, filled as they pass --}}
        <div class="mt-3 flex justify-center gap-1.5" aria-hidden="true">
            @foreach ($steps as $i => $step)
                <span class="h-1.5 w-6 rounded-full bg-gray-200 transition-colors duration-300" data-check-loading-dot="{{ $i }}"></span>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-gray-500">{{ __('يستغرق الفحص عادةً بضع ثوانٍ.') }}</p>
    </div>
</div>
