@php
    $locales = config('trust.locales');
    $locale = app()->getLocale();
    // Pre-launch: visitors only get the launch page, the add-provider form and content pages
    $prelaunch = \App\Support\Launch::appliesToVisitor();
    $prelaunchAdminNotice = ! $prelaunch && \App\Support\Launch::isPrelaunch() && auth()->user()?->is_admin;
    // Labels for the search suggestions dropdown (resources/js/autocomplete.js)
    $autocompleteI18n = [
        'providers' => __('مقدمو الخدمة'),
        'categories' => trans_choice('التصنيفات', 2),
        'verified' => __('موثّق'),
        'empty' => __('لا توجد نتائج مطابقة لـ «:q»'),
        'all' => __('عرض كل النتائج لـ «:q»'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locales[$locale]['dir'] ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $metaTitle = isset($title) ? $title.' | '.config('app.name') : config('app.name');
        $metaDescription = $description ?? \App\Support\Site::text('meta_description', __('منصة تقييمات مستقلة لقنوات التوصيات ومقدمي خدمات نسخ الصفقات والتداول.'));
        // Pages pass :og-image for their own preview; everything else uses the admin's image or the generated site card
        $customImage = ! isset($ogImage) ? \App\Support\Site::fileUrl('og_image') : null;
        $metaImage = $ogImage ?? $customImage ?? route('share-images.site', $locale);
        $metaImageSize = $customImage
            ? \App\Support\Site::imageSize('og_image')
            : [\App\Services\ShareImage::WIDTH, \App\Services\ShareImage::HEIGHT, 'mime' => 'image/png'];
        $favicon = \App\Support\Site::fileUrl('favicon');
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($keywords = \App\Support\Site::text('meta_keywords'))
        <meta name="keywords" content="{{ $keywords }}">
    @endif
    @if (\App\Support\Site::get('noindex'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if ($code = \App\Support\Site::get('google_verification'))
        <meta name="google-site-verification" content="{{ $code }}">
    @endif
    @if ($code = \App\Support\Site::get('bing_verification'))
        <meta name="msvalidate.01" content="{{ $code }}">
    @endif
    <meta name="theme-color" content="{{ \App\Support\Site::get('theme_color') ?: '#191919' }}">
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif

    {{-- Link previews on Telegram, WhatsApp, X, Facebook… --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}">
    <meta property="og:image" content="{{ $metaImage }}">
    @if ($metaImageSize)
        <meta property="og:image:type" content="{{ $metaImageSize['mime'] }}">
        <meta property="og:image:width" content="{{ $metaImageSize[0] }}">
        <meta property="og:image:height" content="{{ $metaImageSize[1] }}">
    @endif
    <meta property="og:image:alt" content="{{ $metaTitle }}">
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_AR' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    @foreach (array_keys($locales) as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}">
    @endforeach
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script type="application/json" id="autocomplete-i18n">@json($autocompleteI18n)</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($gaId = \App\Support\Site::get('ga_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', @json($gaId));</script>
    @endif
    {{-- Admin-provided tracking code (Site settings → الإحصائيات), output as is --}}
    {!! \App\Support\Site::get('head_code') !!}
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-ink text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <x-site-logo />

            <nav class="hidden items-center gap-6 text-sm md:flex">
                <a href="{{ route('home') }}" @class(['hover:text-brand-500', 'text-brand-500' => request()->routeIs('home')])>{{ __('الرئيسية') }}</a>
                @unless ($prelaunch)
                    <a href="{{ route('providers.index') }}" class="hover:text-brand-500">{{ __('تصفح مقدمي الخدمة') }}</a>
                @endunless
                <a href="{{ route('submissions.create') }}" class="hover:text-brand-500">{{ __('أضف مقدم خدمة') }}</a>
                @foreach ($navPages->where('show_in_header', true) as $navPage)
                    <a href="{{ $navPage->url() }}" class="hover:text-brand-500">{{ $navPage->tr('title') }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2 text-sm">
                <x-locale-switcher />
                {{-- Signed-in channel owner: a way back to the page they manage --}}
                @if ($managedChannel = \App\Support\OwnerPortal::owner()?->provider)
                    <a href="{{ route('providers.show', $managedChannel) }}" class="inline-flex items-center gap-1 rounded-full bg-sky-600 px-3 py-1.5 font-semibold hover:bg-sky-700">
                        <x-ui-icon name="shield" class="h-4 w-4" /> {{ __('قناتي') }}
                    </a>
                @endif
                @auth
                    @if (auth()->user()->is_admin)
                        <a href="{{ url('/admin') }}" class="hidden rounded-full px-3 py-1.5 hover:bg-white/10 sm:inline">{{ __('لوحة التحكم') }}</a>
                    @endif
                    <span class="hidden items-center gap-2 text-gray-300 lg:inline-flex">
                        @if (auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="" referrerpolicy="no-referrer" class="h-7 w-7 rounded-full object-cover ring-2 ring-white/20">
                        @endif
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-full px-3 py-1.5 hover:bg-white/10">{{ __('خروج') }}</button>
                    </form>
                @elseif (! $prelaunch)
                    <a href="{{ route('login') }}" class="rounded-full px-3 py-1.5 hover:bg-white/10">{{ __('دخول') }}</a>
                    <a href="{{ route('register') }}" class="whitespace-nowrap rounded-full bg-brand-500 px-4 py-1.5 font-semibold hover:bg-brand-600">{{ __('حساب جديد') }}</a>
                @endauth
            </div>
        </div>
        <nav class="flex gap-4 overflow-x-auto border-t border-white/10 px-4 py-2 text-sm md:hidden">
            <a href="{{ route('home') }}" @class(['whitespace-nowrap', 'text-brand-500' => request()->routeIs('home')])>{{ __('الرئيسية') }}</a>
            @unless ($prelaunch)
                <a href="{{ route('providers.index') }}" class="whitespace-nowrap">{{ __('تصفح مقدمي الخدمة') }}</a>
            @endunless
            <a href="{{ route('submissions.create') }}" class="whitespace-nowrap">{{ __('أضف مقدم خدمة') }}</a>
            @foreach ($navPages->where('show_in_header', true) as $navPage)
                <a href="{{ $navPage->url() }}" class="whitespace-nowrap">{{ $navPage->tr('title') }}</a>
            @endforeach
        </nav>
    </header>

    @if ($prelaunchAdminNotice)
        <div class="bg-amber-400 text-ink">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm font-medium">
                <span>{{ __('وضع ما قبل الإطلاق مفعّل: الزوار يرون صفحة الإطلاق فقط، وأنت ترى الموقع كاملاً لأنك مدير.') }}</span>
                <span class="flex gap-3">
                    <a href="{{ route('home', ['preview' => 'prelaunch']) }}" class="underline">{{ __('معاينة صفحة الإطلاق') }}</a>
                    <a href="{{ url('/admin/launch-settings') }}" class="underline">{{ __('إعدادات الإطلاق') }}</a>
                </span>
            </div>
        </div>
    @endif

    @auth
        @if (! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.*'))
            <div class="border-b border-amber-200 bg-amber-50">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm text-amber-900">
                    @if (auth()->user()->email)
                        <span>{{ __('أكّد بريدك الإلكتروني لتتمكن من كتابة التقييمات.') }}</span>
                        <a href="{{ route('verification.notice') }}" class="font-semibold underline">{{ __('أدخل رمز التأكيد') }}</a>
                    @else
                        <span>{{ __('أضف بريدك الإلكتروني لتتمكن من كتابة التقييمات.') }}</span>
                        <a href="{{ route('verification.notice') }}" class="font-semibold underline">{{ __('أضف البريد') }}</a>
                    @endif
                </div>
            </div>
        @endif
    @endauth

    {{-- Pages that show the message next to their own form pass :hide-flash="true" --}}
    @if (session('success') && ! ($hideFlash ?? false))
        <div class="bg-brand-50 border-b border-brand-100">
            <div class="mx-auto max-w-6xl px-4 py-3 text-sm font-medium text-brand-700">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error') && ! ($hideFlash ?? false))
        <div class="border-b border-amber-200 bg-amber-50" role="alert">
            <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 text-sm font-medium text-amber-900">
                <x-ui-icon name="alert" class="h-5 w-5 shrink-0 text-amber-600" /> {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 bg-ink text-gray-400">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm md:grid-cols-3">
            <div>
                <x-site-logo class="mb-3 text-white" />
                <p>{{ \App\Support\Site::text('footer_about', __('منصة مستقلة لتقييم قنوات التوصيات ومقدمي خدمات نسخ الصفقات والتداول، تعتمد على تجارب المستخدمين الحقيقية.')) }}</p>
                @if ($contactEmail = \App\Support\Site::get('contact_email'))
                    <a href="mailto:{{ $contactEmail }}" class="mt-3 inline-block hover:text-white" dir="ltr">{{ $contactEmail }}</a>
                @endif
                @if ($siteSocials = \App\Support\Site::socials())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($siteSocials as $platform => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ \App\Filament\Pages\SiteSettings::SOCIALS[$platform][0] ?? $platform }}"
                               class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white transition hover:bg-brand-500">
                                <x-ui-icon :name="$platform" class="h-4 w-4" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <div class="mb-2 font-semibold text-white">{{ __('روابط') }}</div>
                <ul class="space-y-1">
                    @unless ($prelaunch)
                        <li><a href="{{ route('providers.index') }}" class="hover:text-white">{{ __('تصفح مقدمي الخدمة') }}</a></li>
                    @endunless
                    <li><a href="{{ route('submissions.create') }}" class="hover:text-white">{{ __('أرسل بيانات مقدم خدمة') }}</a></li>
                    @if (\App\Models\Setting::get('owner_portal_enabled', false))
                        <li><a href="{{ route('owner.login') }}" class="hover:text-white">{{ __('دخول أصحاب القنوات') }}</a></li>
                    @endif
                    @foreach ($navPages->where('show_in_footer', true) as $navPage)
                        <li><a href="{{ $navPage->url() }}" class="hover:text-white">{{ $navPage->tr('title') }}</a></li>
                    @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($locales as $code => $meta)
                        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" class="{{ $code === $locale ? 'font-semibold text-white' : 'hover:text-white' }}">{{ $meta['name'] }}</a>
                    @endforeach
                </div>
            </div>
            <div>
                <div class="mb-2 font-semibold text-white">{{ __('تنبيه') }}</div>
                <p>{{ \App\Support\Site::text('disclaimer', __('التقييمات تعبّر عن آراء أصحابها. التداول ينطوي على مخاطر عالية، ولا يُعد أي محتوى هنا نصيحة مالية.')) }}</p>
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs">© {{ date('Y') }} {{ config('app.name') }}</div>
    </footer>
</body>
</html>
