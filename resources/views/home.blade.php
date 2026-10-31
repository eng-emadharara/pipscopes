<x-layouts.app>
    <x-structured-data :items="\App\Support\StructuredData::site()" />

    {{-- Hero: search, popular channels and numbers --}}
    @php
        // A zero would read as a weakness, so the number shows once there is something to show
        $showImpersonators = $stats['impersonators'] > 0;
    @endphp
    <section class="hero-bg relative isolate overflow-hidden bg-ink text-white">
        <div class="mx-auto max-w-3xl px-4 pb-12 pt-12 text-center md:pb-14 md:pt-16">
            <h1 class="text-3xl font-bold leading-tight md:text-5xl">{{ __('اقرأ التقييمات. اختر بثقة.') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-gray-300">{{ __('تجارب حقيقية من متداولين حول قنوات التوصيات، خدمات نسخ الصفقات، ومقدمي خدمات التداول قبل أن تشترك.') }}</p>

            <form action="{{ route('providers.index') }}" method="GET" class="mx-auto mt-8 flex max-w-xl items-center rounded-full bg-white p-1.5 shadow-lg shadow-black/30 ring-1 ring-white/10">
                <x-ui-icon name="search" class="mx-3 h-5 w-5 shrink-0 text-gray-400" />
                <input type="search" name="q" data-autocomplete="{{ route('search.suggest') }}" placeholder="{{ __('ابحث باسم القناة أو مقدم الخدمة أو رابط التيليجرام…') }}" class="min-w-0 flex-1 bg-transparent py-2 text-sm text-ink placeholder-gray-400 focus:outline-none" aria-label="{{ __('بحث') }}">
                <button class="btn-primary shrink-0">{{ __('بحث') }}</button>
            </form>

            {{-- The most reviewed channels, one tap away; always one line (phones keep the first two) --}}
            @if ($popular->isNotEmpty())
                <div class="mx-auto mt-4 flex max-w-xl items-center justify-center gap-2 text-xs">
                    <span class="shrink-0 text-gray-400">{{ __('شائع:') }}</span>
                    @foreach ($popular as $item)
                        <a href="{{ route('providers.show', $item) }}" data-track="hero_popular_click" data-track-params='@json(['provider' => $item->slug])'
                           @class(['min-w-0 truncate rounded-full border border-white/15 bg-white/5 px-3 py-1 text-gray-200 transition hover:border-brand-500 hover:bg-brand-500/10 hover:text-white', 'max-sm:hidden' => $loop->index >= 2])><bdi>{{ $item->name }}</bdi></a>
                    @endforeach
                </div>
            @endif

            {{-- divide-x is logical in Tailwind 4, so it needs no RTL reverse --}}
            <dl @class(['mx-auto mt-8 grid divide-x divide-white/10', $showImpersonators ? 'max-w-xl grid-cols-3' : 'max-w-sm grid-cols-2'])>
                <div class="flex flex-col-reverse items-center gap-0.5 px-2">
                    <dt class="text-xs text-gray-400 md:text-sm">{{ __('مقدم خدمة') }}</dt>
                    <dd class="text-xl font-bold tabular-nums md:text-2xl">{{ number_format($stats['providers']) }}</dd>
                </div>
                <div class="flex flex-col-reverse items-center gap-0.5 px-2">
                    <dt class="text-xs text-gray-400 md:text-sm">{{ __('تقييم') }}</dt>
                    <dd class="text-xl font-bold tabular-nums md:text-2xl">{{ number_format($stats['reviews']) }}</dd>
                </div>
                {{-- What sets us apart: impersonators caught; links to the account checker when it's on --}}
                @php($checker = collect($layout)->contains('key', 'account_check'))
                @if ($showImpersonators)
                <div class="flex flex-col-reverse items-center gap-0.5 px-2">
                    <dt class="inline-flex items-center gap-1 text-xs text-gray-400 md:text-sm">
                        <x-ui-icon name="shield" class="h-3.5 w-3.5 text-amber-400" />
                        @if ($checker)
                            <a href="#account-check" class="underline decoration-white/20 underline-offset-4 hover:text-white hover:decoration-white">{{ __('حساب منتحل تم كشفه') }}</a>
                        @else
                            {{ __('حساب منتحل تم كشفه') }}
                        @endif
                    </dt>
                    <dd class="text-xl font-bold tabular-nums text-amber-400 md:text-2xl">{{ number_format($stats['impersonators']) }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </section>

    {{-- Sections in the order and with the titles set in Admin → Home sections (App\Support\HomeSections) --}}
    @foreach ($layout as $section)
        @php($first = $loop->first)
        @switch($section['key'])
            @case('categories')
                @include('home._categories')
                @break
            @case('picks')
                @include('home._picks')
                @break
            @case('account_check')
                <section class="mx-auto mt-10 max-w-6xl px-4">
                    @include('home._account-check')
                </section>
                @break
            @case('top_rated')
                @include('home._top-rated')
                @break
            @case('alerts')
                @if ($alerts && ($alerts['impersonators']->isNotEmpty() || $alerts['flagged']->isNotEmpty()))
                    @include('home._alerts')
                @endif
                @break
            @case('latest_reviews')
                @include('home._latest-reviews')
                @break
            @case('trust')
                @include('home._trust')
                @break
            @case('telegram_join')
                {{-- Shown only once the Telegram link is set in the site settings --}}
                @if (filled(\App\Support\Site::socials()['telegram'] ?? null))
                    @include('home._telegram-join')
                @endif
                @break
            @case('cta')
                @include('home._cta')
                @break
        @endswitch
    @endforeach
</x-layouts.app>
