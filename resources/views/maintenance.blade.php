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
    <meta name="theme-color" content="{{ \App\Support\Site::get('theme_color') ?: '#191919' }}">
    <link rel="icon" href="{{ $favicon ?? asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/maintenance.js'])
</head>
<body class="flex min-h-screen flex-col" data-maintenance @if ($preview) data-preview="1" @endif>
    <header class="bg-ink text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 text-sm">
            <x-site-logo />
            <x-locale-switcher />
        </div>
    </header>

    @if ($preview)
        <div class="bg-amber-400 text-ink">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm font-medium">
                <span>{{ __('معاينة: هذا ما يراه الزوار أثناء الصيانة.') }}</span>
                <a href="{{ url('/admin/maintenance') }}" class="font-semibold underline">{{ __('إدارة الصيانة') }}</a>
            </div>
        </div>
    @endif

    <main class="flex flex-1 items-center">
        <div class="mx-auto w-full max-w-xl px-4 py-12 text-center sm:py-16">
            {{-- Illustration: a review card with a gear on its corner --}}
            <svg viewBox="0 0 200 150" aria-hidden="true" class="mx-auto mb-8 w-72 sm:w-96">
                <defs>
                    <symbol id="maint-star" viewBox="0 0 24 24"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z" /></symbol>
                </defs>

                <circle cx="100" cy="75" r="72" fill="var(--color-brand-50)" />

                <rect x="30" y="34" width="132" height="86" rx="12" fill="#fff" stroke="#e5e7eb" stroke-width="1.5" />
                <rect x="46" y="50" width="64" height="7" rx="3.5" fill="#191919" opacity=".85" />
                @foreach (range(0, 4) as $i)
                    <rect x="{{ 46 + $i * 20 }}" y="68" width="16" height="16" rx="2.5" fill="{{ $i < 4 ? 'var(--color-rate-excellent)' : 'var(--color-rate-none)' }}" />
                    <use href="#maint-star" x="{{ 48.5 + $i * 20 }}" y="70.5" width="11" height="11" fill="#fff" />
                @endforeach
                <rect x="46" y="96" width="88" height="6" rx="3" fill="#f3f4f6" />

                {{-- Gear: teeth are a dashed ring around a solid disc --}}
                <circle cx="160" cy="114" r="22" fill="none" stroke="#191919" stroke-width="8" stroke-dasharray="7.2 7.2" />
                <circle cx="160" cy="114" r="20" fill="#191919" />
                <circle cx="160" cy="114" r="7" fill="var(--color-brand-500)" />
            </svg>

            <h1 class="text-3xl leading-tight font-bold text-balance sm:text-4xl sm:leading-tight">{{ $headline }}</h1>
            <p class="mx-auto mt-4 max-w-lg leading-relaxed text-pretty text-gray-600 sm:text-lg">{{ $message }}</p>

            @if ($showCountdown)
                <div class="mt-10" data-countdown="{{ $endsAt->toIso8601String() }}" data-reload-on-end="{{ $endsAutomatically && ! $preview ? '1' : '0' }}">
                    <p class="mb-3 text-sm font-semibold text-gray-800">{{ __('نعود خلال') }}</p>
                    <div dir="ltr" class="mx-auto grid max-w-sm grid-cols-4 gap-2 sm:gap-3">
                        @foreach (['days' => __('يوم'), 'hours' => __('ساعة'), 'minutes' => __('دقيقة'), 'seconds' => __('ثانية')] as $unit => $unitLabel)
                            <div class="card px-1 py-3">
                                <div class="text-2xl font-bold tabular-nums sm:text-3xl" data-unit="{{ $unit }}">--</div>
                                <div class="mt-0.5 text-xs text-gray-500" dir="auto">{{ $unitLabel }}</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm text-gray-500">{{ __('الموعد المتوقع للعودة:') }} <span data-launch-date></span></p>
                    <p class="mt-3 hidden font-semibold text-brand-600" data-ended>{{ $endsAutomatically ? __('انتهت الصيانة! جارٍ فتح الموقع…') : __('نضع اللمسات الأخيرة، شكراً لصبرك…') }}</p>
                </div>
            @endif

            @if ($socials || $contactEmail)
                <div class="mt-10 border-t border-gray-200 pt-8">
                    @if ($socials)
                        <p class="text-sm font-semibold text-gray-800">{{ __('تابعنا لآخر التحديثات') }}</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2.5">
                            @foreach ($socials as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ \App\Filament\Pages\SiteSettings::SOCIALS[$platform][0] ?? $platform }}"
                                   class="grid h-11 w-11 place-items-center rounded-full border border-gray-200 bg-white text-ink transition hover:-translate-y-0.5 hover:border-brand-500 hover:bg-brand-500 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                                    <x-ui-icon :name="$platform" class="h-[18px] w-[18px]" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($contactEmail)
                        <p @class(['text-sm text-gray-600', 'mt-6' => $socials])>
                            {{ $socials ? __('أو تواصل معنا عبر البريد الإلكتروني:') : __('تواصل معنا عبر البريد الإلكتروني:') }}
                            <a href="mailto:{{ $contactEmail }}" dir="ltr" class="font-semibold text-brand-600 hover:underline">{{ $contactEmail }}</a>
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </main>

    <footer class="border-t border-gray-200 py-5 text-center text-xs text-gray-500">© {{ date('Y') }} {{ config('app.name') }}</footer>
</body>
</html>
