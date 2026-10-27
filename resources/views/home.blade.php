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

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto -mt-8 max-w-6xl px-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <a href="{{ route('categories.show', $category) }}" class="card flex flex-col items-center gap-1 p-4 text-center shadow-sm transition hover:border-gray-400">
                        <span class="text-2xl">{{ $category->icon }}</span>
                        <span class="text-sm font-semibold">{{ $category->tr('name') }}</span>
                        <span class="text-xs text-gray-500">{{ trans_choice(':count مزود', $category->providers_count) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($sections['account_check'])
        <section class="mx-auto mt-10 max-w-6xl px-4">
            @include('home._account-check')
        </section>
    @endif

    {{-- Top rated --}}
    <section class="mx-auto mt-14 max-w-6xl px-4">
        <div class="mb-5 flex items-end justify-between">
            <h2 class="text-2xl font-bold">{{ __('الأعلى تقييماً') }}</h2>
            <a href="{{ route('providers.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('عرض الكل') }} <span class="inline-block rtl:rotate-180">→</span></a>
        </div>
        @if ($featured->isEmpty())
            <div class="card p-10 text-center text-gray-500">{{ __('لا يوجد مقدمو خدمة بعد.') }}</div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featured as $provider)
                    <x-provider-card :provider="$provider" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($alerts && ($alerts['impersonators']->isNotEmpty() || $alerts['flagged']->isNotEmpty()))
        @include('home._alerts')
    @endif

    {{-- Latest reviews --}}
    @if ($latestReviews->isNotEmpty())
        <section class="mx-auto mt-14 max-w-6xl px-4">
            <h2 class="mb-5 text-2xl font-bold">{{ __('أحدث التقييمات') }}</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($latestReviews as $review)
                    <article class="card flex flex-col gap-2 p-5">
                        <x-stars :rating="$review->rating" size="sm" />
                        <h3 dir="auto" class="text-start font-bold">{{ $review->title }}</h3>
                        <p dir="auto" class="line-clamp-3 text-start text-sm text-gray-600">{{ $review->body }}</p>
                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
                            <span class="shrink-0">{{ $review->displayName() }}</span>
                            <a href="{{ route('providers.show', $review->provider) }}" title="{{ $review->provider->name }}" class="line-clamp-2 min-w-0 break-words text-end font-semibold text-ink hover:underline"><bdi>{{ $review->provider->name }}</bdi></a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($sections['trust'])
        @include('home._trust')
    @endif

    {{-- Shown only once the Telegram link is set in the site settings --}}
    @if ($sections['telegram_join'] && filled(\App\Support\Site::socials()['telegram'] ?? null))
        @include('home._telegram-join')
    @endif

    {{-- CTA --}}
    <section class="mx-auto mt-14 max-w-6xl px-4">
        <div class="card flex flex-col items-center justify-between gap-4 bg-brand-50 p-8 md:flex-row">
            <div>
                <h2 class="text-xl font-bold">{{ __('تقدّم خدمة توصيات أو نسخ صفقات؟') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('أرسل لنا بياناتك وروابطك وسنضيفك إلى المنصة بعد المراجعة.') }}</p>
            </div>
            <a href="{{ route('submissions.create') }}" class="btn-dark">{{ __('أرسل بياناتك') }}</a>
        </div>
    </section>
</x-layouts.app>
