@php
    $headline = \App\Support\Launch::text('headline', __('قريباً: منصة مستقلة لتقييم قنوات التوصيات ومقدمي الخدمات'));
    $subheadline = \App\Support\Launch::text('subheadline', __('نجهّز الآن قائمة قنوات التوصيات وخدمات نسخ الصفقات قبل الإطلاق. هل تدير قناة أو تعرف قناة تستحق التقييم؟ أضفها الآن لتكون جاهزة للتقييم يوم الإطلاق.'));
@endphp
<x-layouts.app :title="__('قريباً')" :description="$subheadline" :hide-flash="true">
    <section class="bg-ink pb-16 pt-14 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-500">
                <span class="h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                {{ __('قبل الإطلاق') }}
            </span>
            <h1 class="mt-5 text-3xl font-bold leading-tight md:text-5xl">{{ $headline }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-gray-300">{{ $subheadline }}</p>

            @if ($showCountdown)
                <div class="mt-10" data-countdown="{{ $launchAt->toIso8601String() }}" data-reload-on-end="{{ $opensAutomatically ? '1' : '0' }}">
                    <p class="mb-4 text-sm text-gray-400">{{ __('ينطلق الموقع خلال') }}</p>
                    <div class="mx-auto grid max-w-md grid-cols-4 gap-2 sm:gap-3">
                        @foreach (['days' => __('يوم'), 'hours' => __('ساعة'), 'minutes' => __('دقيقة'), 'seconds' => __('ثانية')] as $unit => $unitLabel)
                            <div class="rounded-2xl bg-white/5 px-1 py-3 ring-1 ring-white/10 sm:py-4">
                                <div class="text-3xl font-bold tabular-nums sm:text-4xl" data-unit="{{ $unit }}">--</div>
                                <div class="mt-1 text-xs text-gray-400">{{ $unitLabel }}</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm text-gray-400" data-launch-date></p>
                    <p class="mt-4 hidden text-lg font-semibold text-brand-500" data-ended>{{ $opensAutomatically ? __('انطلقنا! جارٍ فتح الموقع…') : __('قريباً جداً…') }}</p>
                </div>
            @endif

            <a href="#add-provider" class="btn-primary mt-10 px-7 py-3 text-base">{{ __('أضف قناة أو مقدم خدمة الآن') }}</a>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto -mt-8 max-w-4xl px-4">
        <div class="grid gap-3 md:grid-cols-3">
            @foreach ([
                ['📝', __('أضف القناة'), __('أرسل اسم القناة ورابط التيليجرام وحساباتها على السوشيال ميديا.')],
                ['🔍', __('نراجع البيانات'), __('يتحقق فريقنا من الروابط ويجهّز صفحة لكل مقدم خدمة.')],
                ['⭐', __('يوم الإطلاق'), __('يبدأ المستخدمون بتقييم القنوات ومشاركة تجاربهم الحقيقية بكل شفافية.')],
            ] as $i => [$icon, $stepTitle, $stepText])
                <div class="card p-5 shadow-sm">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="text-xl">{{ $icon }}</span>
                        <span class="text-xs font-bold text-brand-600">{{ $i + 1 }}</span>
                        <h2 class="font-bold">{{ $stepTitle }}</h2>
                    </div>
                    <p class="text-sm leading-relaxed text-gray-600">{{ $stepText }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- The form --}}
    <section class="mx-auto mt-14 max-w-3xl px-4">
        <h2 class="text-2xl font-bold md:text-3xl">{{ __('أضف قناة توصيات أو مقدم خدمة') }}</h2>
        <p class="mt-2 text-gray-600">{{ __('سواء كنت صاحب القناة أو مشتركاً فيها، كل قناة تضيفها تساعد متداولاً آخر على اتخاذ قرار أفضل.') }}</p>

        @if (session('success'))
            <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-4 font-medium text-brand-700">
                ✅ {{ session('success') }} {{ __('يمكنك إضافة قناة أخرى.') }}
            </div>
        @endif

        <div class="mt-8">
            @include('submissions._form')
        </div>
    </section>
</x-layouts.app>
