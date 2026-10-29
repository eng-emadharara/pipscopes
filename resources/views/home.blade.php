<x-layouts.app>
    <x-structured-data :items="\App\Support\StructuredData::site()" />

    {{-- Hero --}}
    <section class="bg-ink pb-16 pt-12 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <h1 class="text-3xl font-bold leading-tight md:text-5xl">{{ __('اقرأ التقييمات. اختر بثقة.') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-gray-300">{{ __('تجارب حقيقية من متداولين حول قنوات التوصيات، خدمات نسخ الصفقات، ومقدمي خدمات التداول قبل أن تشترك.') }}</p>

            <form action="{{ route('providers.index') }}" method="GET" class="mx-auto mt-8 flex max-w-xl items-center rounded-full bg-white p-1.5 shadow-lg">
                <x-ui-icon name="search" class="mx-3 h-5 w-5 shrink-0 text-gray-400" />
                <input type="search" name="q" data-autocomplete="{{ route('search.suggest') }}" placeholder="{{ __('ابحث باسم القناة أو مقدم الخدمة أو رابط التيليجرام…') }}" class="min-w-0 flex-1 bg-transparent py-2 text-sm text-ink placeholder-gray-400 focus:outline-none" aria-label="{{ __('بحث') }}">
                <button class="btn-primary shrink-0">{{ __('بحث') }}</button>
            </form>

            <div class="mt-6 flex justify-center gap-8 text-sm text-gray-400">
                <span><strong class="text-lg text-white">{{ number_format($stats['providers']) }}</strong> {{ __('مقدم خدمة') }}</span>
                <span><strong class="text-lg text-white">{{ number_format($stats['reviews']) }}</strong> {{ __('تقييم') }}</span>
            </div>
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
