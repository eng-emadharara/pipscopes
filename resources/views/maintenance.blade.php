{{-- Shown on every public page while maintenance mode is on (App\Http\Middleware\MaintenanceMode).
     A page of its own rather than the site layout: the menus there lead to pages that are closed. --}}
@php
    $locales = config('trust.locales');
    $locale = app()->getLocale();
    $headline = \App\Support\Maintenance::text('headline', __('نعمل على تحسين المنصة من أجلك'));
    $message = \App\Support\Maintenance::text('message', __('نجري حالياً أعمال صيانة وتحديثات مجدولة لنقدّم لك تجربة أسرع وأكثر أماناً. سنعود خلال وقت قصير، شكراً لصبرك.'));
    $contactEmail = \App\Models\Setting::get('maintenance_show_contact', true) ? \App\Support\Site::get('contact_email') : null;
    $socials = \App\Models\Setting::get('maintenance_show_socials', true) ? \App\Support\Site::socials() : [];
    $favicon = \App\Support\Site::fileUrl('favicon');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locales[$locale]['dir'] ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('تحت الصيانة') }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $message }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b0d0c">
    <link rel="icon" href="{{ $favicon ?? asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/maintenance.js'])
</head>
<body class="relative min-h-screen overflow-x-hidden bg-[#0b0d0c] text-white" data-maintenance @if ($preview) data-preview="1" @endif>
    {{-- Backdrop: grid and two slow green glows --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="maint-grid absolute inset-0"></div>
        <div class="absolute -top-48 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 animate-drift rounded-full bg-brand-500/20 blur-[130px] motion-reduce:animate-none"></div>
        <div class="absolute -bottom-56 -right-40 h-[30rem] w-[30rem] animate-drift rounded-full bg-teal-400/10 blur-[120px] [animation-delay:-9s] motion-reduce:animate-none"></div>
    </div>

    <div class="relative z-10 flex min-h-screen flex-col">
        @if ($preview)
            <div class="bg-amber-400 text-ink">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm font-medium">
                    <span>{{ __('معاينة: هذا ما يراه الزوار أثناء الصيانة.') }}</span>
                    <a href="{{ url('/admin/maintenance') }}" class="font-semibold underline">{{ __('إدارة الصيانة') }}</a>
                </div>
            </div>
        @endif

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 text-sm">
            <x-site-logo class="text-white" />
            <x-locale-switcher />
        </header>

        <main class="flex flex-1 items-center">
            <div class="mx-auto w-full max-w-3xl px-5 py-10 text-center">
                {{-- Gear inside two turning rings --}}
                <div aria-hidden="true" class="relative mx-auto mb-10 grid h-40 w-40 place-items-center sm:h-44 sm:w-44">
                    <div class="absolute inset-0 animate-[spin_40s_linear_infinite] rounded-full border border-dashed border-white/15 motion-reduce:animate-none"></div>
                    <div class="maint-arc absolute inset-4 animate-[spin_5s_linear_infinite] rounded-full motion-reduce:animate-none"></div>
                    <div class="absolute inset-4 animate-[spin_9s_linear_infinite_reverse] motion-reduce:animate-none">
                        <span class="absolute -top-1 left-1/2 h-2.5 w-2.5 -translate-x-1/2 rounded-full bg-brand-500 shadow-[0_0_18px_4px_rgb(0_182_122/0.6)]"></span>
                    </div>
                    <div class="relative grid h-24 w-24 place-items-center rounded-full bg-white/[0.04] shadow-[inset_0_1px_0_rgb(255_255_255/0.08),0_20px_60px_-15px_rgb(0_182_122/0.45)] ring-1 ring-white/10 backdrop-blur-xl">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-11 w-11 animate-[spin_14s_linear_infinite] text-brand-500 motion-reduce:animate-none">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                </div>

                <span class="inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-400/10 px-3.5 py-1.5 text-xs font-semibold text-amber-300">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75 motion-reduce:animate-none"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-400"></span>
                    </span>
                    {{ __('صيانة مجدولة قيد التنفيذ') }}
                </span>

                <h1 class="mt-6 bg-linear-to-b from-white to-white/60 bg-clip-text text-3xl leading-tight font-bold text-balance text-transparent sm:text-5xl sm:leading-tight">{{ $headline }}</h1>
                <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-pretty text-gray-400 sm:text-lg">{{ $message }}</p>

                {{-- Indeterminate progress; kept left-to-right so the sweep looks the same in both directions --}}
                <div dir="ltr" class="mx-auto mt-10 h-1 w-full max-w-xs overflow-hidden rounded-full bg-white/10">
                    <div class="h-full w-1/3 animate-shimmer rounded-full bg-linear-to-r from-transparent via-brand-500 to-transparent motion-reduce:animate-none"></div>
                </div>

                @if ($showCountdown)
                    <div class="mt-12" data-countdown="{{ $endsAt->toIso8601String() }}" data-reload-on-end="{{ $endsAutomatically && ! $preview ? '1' : '0' }}">
                        <p class="mb-4 text-sm text-gray-400">{{ __('نعود خلال') }}</p>
                        <div dir="ltr" class="mx-auto grid max-w-md grid-cols-4 gap-2 sm:gap-3">
                            @foreach (['days' => __('يوم'), 'hours' => __('ساعة'), 'minutes' => __('دقيقة'), 'seconds' => __('ثانية')] as $unit => $unitLabel)
                                <div class="rounded-2xl bg-white/[0.04] px-1 py-3 shadow-[inset_0_1px_0_rgb(255_255_255/0.06)] ring-1 ring-white/10 backdrop-blur sm:py-4">
                                    <div class="text-3xl font-bold tabular-nums sm:text-4xl" data-unit="{{ $unit }}">--</div>
                                    <div class="mt-1 text-xs text-gray-500" dir="auto">{{ $unitLabel }}</div>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-4 text-sm text-gray-500">{{ __('الموعد المتوقع للعودة:') }} <span data-launch-date></span></p>
                        <p class="mt-4 hidden text-lg font-semibold text-brand-500" data-ended>{{ $endsAutomatically ? __('انتهت الصيانة! جارٍ فتح الموقع…') : __('نضع اللمسات الأخيرة، شكراً لصبرك…') }}</p>
                    </div>
                @endif

                @unless ($preview)
                    <p class="mt-10 inline-flex items-center gap-2 text-xs text-gray-500">
                        <span class="h-3 w-3 animate-spin rounded-full border-2 border-gray-600 border-t-brand-500 motion-reduce:animate-none"></span>
                        {{ __('هذه الصفحة تتحقق تلقائياً وستعيدك إلى الموقع فور انتهاء الصيانة.') }}
                    </p>
                @endunless

                @if ($contactEmail || $socials)
                    <div class="mx-auto mt-12 max-w-md rounded-2xl border border-white/10 bg-white/[0.03] p-5 backdrop-blur">
                        <p class="text-sm text-gray-400">{{ __('تحتاج مساعدة عاجلة؟ تواصل معنا') }}</p>
                        @if ($contactEmail)
                            <a href="mailto:{{ $contactEmail }}" dir="ltr" class="mt-3 inline-flex items-center gap-2 font-semibold text-white transition hover:text-brand-500">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                {{ $contactEmail }}
                            </a>
                        @endif
                        @if ($socials)
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @foreach ($socials as $platform => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ \App\Filament\Pages\SiteSettings::SOCIALS[$platform][0] ?? $platform }}"
                                       class="grid h-10 w-10 place-items-center rounded-full bg-white/5 text-gray-300 ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:bg-brand-500 hover:text-white hover:ring-brand-500">
                                        <x-ui-icon :name="$platform" class="h-4 w-4" />
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-gray-600">© {{ date('Y') }} {{ config('app.name') }}</footer>
    </div>
</body>
</html>
