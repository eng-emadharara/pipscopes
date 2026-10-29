@php
    $headline = \App\Support\Launch::text('headline', __('قريباً: منصة مستقلة لتقييم قنوات التوصيات ومقدمي الخدمات'));
    $subheadline = \App\Support\Launch::text('subheadline', __('نجهّز الآن قائمة قنوات التوصيات وخدمات نسخ الصفقات قبل الإطلاق. هل تدير قناة أو تعرف قناة تستحق التقييم؟ أضفها الآن لتكون جاهزة للتقييم يوم الإطلاق.'));
    $socials = \App\Support\Site::socials();
    $telegram = $socials['telegram'] ?? null;
    // Shown once there are enough channels to be worth saying
    $readyChannels = \App\Models\Provider::active()->count();
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

            {{-- Sign-up is closed until launch: following the channel is how visitors hear the moment it opens --}}
            <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                @if ($telegram)
                    <a href="{{ $telegram }}" target="_blank" rel="noopener" data-track="telegram_join" data-track-params='{"location":"prelaunch_hero"}'
                       class="inline-flex items-center justify-center gap-2 rounded-full bg-[#229ED9] px-7 py-3 text-base font-semibold text-white transition hover:bg-[#1a8bc2]">
                        <x-ui-icon name="telegram" class="h-5 w-5" /> {{ __('تابعنا لتعرف لحظة الإطلاق') }}
                    </a>
                @endif
                <a href="#add-provider" @class(['px-7 py-3 text-base', 'btn-primary' => ! $telegram, 'inline-flex items-center justify-center rounded-full border border-white/25 font-semibold text-white transition hover:bg-white/10' => $telegram])>
                    {{ __('أضف قناة أو مقدم خدمة الآن') }}
                </a>
            </div>

            @if ($readyChannels >= 10)
                <p class="mt-6 text-sm text-gray-400">🔥 {{ trans_choice(':count قناة جاهزة للتقييم يوم الإطلاق', $readyChannels) }}</p>
            @endif
        </div>
    </section>

    {{-- What traders get on launch day --}}
    <section class="mx-auto -mt-8 max-w-5xl px-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['star', __('تجارب حقيقية'), __('اقرأ تقييمات متداولين جرّبوا القناة قبل أن تدفع اشتراكك.')],
                ['search', __('افحص الحساب قبل الدفع'), __('تأكد أن الحساب الذي يراسلك هو القناة الرسمية وليس نسخة مزيفة.')],
                ['alert', __('كشف المنتحلين'), __('قائمة بالحسابات التي تنتحل أسماء القنوات المعروفة لسرقة المشتركين.')],
                ['shield', __('بلا حذف مقابل المال'), __('لا تستطيع أي قناة شراء تقييم أو إخفاء تجربة سلبية.')],
            ] as [$icon, $featureTitle, $featureText])
                <div class="card p-4 shadow-sm sm:p-5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700 sm:h-10 sm:w-10"><x-ui-icon :name="$icon" class="h-5 w-5" /></span>
                    <h2 class="mt-3 text-sm font-bold sm:text-base">{{ $featureTitle }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-gray-600 sm:text-sm">{{ $featureText }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How adding a channel works --}}
    <section class="mx-auto mt-14 max-w-4xl px-4">
        <h2 class="mb-4 text-center text-lg font-bold text-gray-700">{{ __('تدير قناة أو تعرف قناة؟ هكذا تضيفها') }}</h2>
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

    {{-- Don't miss the launch: follow and share --}}
    @if ($socials)
        <section class="mx-auto mt-14 max-w-3xl px-4">
            <div class="card flex flex-col items-center gap-4 p-6 text-center md:flex-row md:text-start">
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold">{{ __('لا تفوّت الإطلاق') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('تابعنا لتصلك لحظة الافتتاح، وشارك الصفحة مع متداول تعرفه.') }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center justify-center gap-2">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           @if ($network === 'telegram') data-track="telegram_join" data-track-params='{"location":"prelaunch"}' @else data-track="social_follow" data-track-params='@json(['platform' => $network, 'location' => 'prelaunch'])' @endif
                           class="grid h-10 w-10 place-items-center rounded-full border border-gray-200 text-gray-700 transition hover:border-gray-400 hover:text-ink"
                           aria-label="{{ \App\Filament\Pages\SiteSettings::SOCIALS[$network][0] ?? $network }}">
                            <x-ui-icon :name="$network" class="h-5 w-5" />
                        </a>
                    @endforeach
                    <x-share-buttons :url="route('home')" :text="$headline" />
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
