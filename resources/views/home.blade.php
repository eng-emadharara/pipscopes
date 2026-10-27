@php
    // Real data for the hero's preview: the best-rated channel and the newest review
    $spotlight = $featured->firstWhere('reviews_count', '>', 0);
    $spotlightReview = $latestReviews->first();
    $hasPreview = $spotlight || $spotlightReview;
@endphp
<x-layouts.app>
    {{-- Hero --}}
    <section class="hero-surface text-white">
        <div @class([
            'mx-auto grid max-w-6xl items-center gap-12 px-4 pb-28 pt-14 md:pt-20',
            'lg:grid-cols-[minmax(0,1.15fr)_minmax(0,.85fr)]' => $hasPreview,
            'text-center' => ! $hasPreview,
        ])>
            <div @class(['mx-auto max-w-2xl' => ! $hasPreview])>
                <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-gray-300 backdrop-blur">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-60 motion-reduce:hidden"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-400"></span>
                    </span>
                    {{ __('منصة تقييمات مستقلة') }}
                </p>

                <h1 class="mt-5 text-4xl font-bold leading-[1.15] tracking-tight text-balance md:text-6xl md:leading-[1.1]">
                    {{ __('اقرأ التقييمات.') }}
                    <span class="block text-brand-400">{{ __('اختر بثقة.') }}</span>
                </h1>
                <p @class(['mt-5 max-w-xl text-pretty text-base leading-relaxed text-gray-300 md:text-lg', 'mx-auto' => ! $hasPreview])>
                    {{ __('تجارب حقيقية من متداولين حول قنوات التوصيات، خدمات نسخ الصفقات، ومقدمي خدمات التداول قبل أن تشترك.') }}
                </p>

                <form action="{{ route('providers.index') }}" method="GET" @class(['mt-8 max-w-xl', 'mx-auto' => ! $hasPreview])>
                    <div data-autocomplete-box class="flex items-center rounded-2xl bg-white p-1.5 shadow-2xl shadow-black/40 ring-1 ring-white/10 transition focus-within:ring-4 focus-within:ring-brand-500/40">
                        <x-ui-icon name="search" class="mx-3 h-5 w-5 shrink-0 text-gray-400" />
                        <input type="search" name="q" data-autocomplete="{{ route('search.suggest') }}" placeholder="{{ __('ابحث باسم القناة أو مقدم الخدمة أو رابط التيليجرام…') }}" class="min-w-0 flex-1 bg-transparent py-3 text-sm text-ink placeholder-gray-400 focus:outline-none md:text-base" aria-label="{{ __('بحث') }}">
                        <button class="btn-primary shrink-0 rounded-xl px-6 py-3 active:scale-[.98]">{{ __('بحث') }}</button>
                    </div>
                </form>

                @if ($categories->isNotEmpty())
                    <div @class(['mt-4 flex flex-wrap items-center gap-2 text-xs', 'justify-center' => ! $hasPreview])>
                        <span class="text-gray-400">{{ __('تصفّح:') }}</span>
                        @foreach ($categories->take(4) as $category)
                            <a href="{{ route('categories.show', $category) }}" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-gray-200 transition hover:border-white/30 hover:bg-white/10">
                                {{ $category->icon }} {{ $category->tr('name') }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <dl @class(['mt-10 flex flex-wrap gap-x-10 gap-y-4', 'justify-center' => ! $hasPreview])>
                    @foreach ([
                        [$stats['providers'], __('مقدم خدمة')],
                        [$stats['reviews'], __('تقييم')],
                        [$categories->count(), __('تصنيف')],
                    ] as [$value, $label])
                        <div class="border-white/10 ps-0 [&:not(:first-child)]:border-s [&:not(:first-child)]:ps-10">
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="text-3xl font-bold tabular-nums tracking-tight">{{ number_format($value) }}</dd>
                            <dd class="mt-0.5 text-sm text-gray-400">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Live preview: purely decorative, the same content is listed further down --}}
            @if ($hasPreview)
                <div class="relative hidden lg:block" aria-hidden="true">
                    <div class="absolute -inset-6 rounded-[2rem] bg-gradient-to-b from-white/[.06] to-transparent"></div>

                    @if ($spotlight)
                        <div class="relative rounded-2xl bg-white p-5 text-ink shadow-2xl shadow-black/40 lg:-rotate-2">
                            <div class="flex items-center gap-3">
                                <x-avatar :provider="$spotlight" size="h-12 w-12 text-lg" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-bold">
                                        <bdi>{{ $spotlight->name }}</bdi>
                                        @if ($spotlight->is_verified)
                                            <x-ui-icon name="check" class="inline-block h-4 w-4 align-[-2px] text-brand-500" />
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-gray-500">{{ $spotlight->tr('service_type') ?: $spotlight->categories->first()?->tr('name') }}</p>
                                </div>
                                <span class="rounded-lg bg-brand-50 px-2 py-1 text-xs font-bold text-brand-700">{{ __('الأعلى تقييماً') }}</span>
                            </div>
                            <div class="mt-4 flex items-end justify-between gap-4 rounded-xl bg-sand px-4 py-3">
                                <div>
                                    <p class="text-4xl font-bold tabular-nums tracking-tight">{{ number_format($spotlight->rating_avg, 1) }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Models\Provider::ratingLabel((float) $spotlight->rating_avg) }} · {{ trans_choice(':count تقييم', $spotlight->reviews_count) }}</p>
                                </div>
                                <x-stars :rating="$spotlight->rating_avg" />
                            </div>
                        </div>
                    @endif

                    @if ($spotlightReview)
                        <div @class([
                            'relative rounded-2xl border border-white/10 bg-white/[.07] p-5 text-white shadow-2xl shadow-black/30 backdrop-blur-md',
                            'mt-5 lg:ms-12 lg:rotate-1' => $spotlight,
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <x-stars :rating="$spotlightReview->rating" size="sm" />
                                <span class="text-xs text-gray-400">{{ $spotlightReview->created_at->diffForHumans() }}</span>
                            </div>
                            <p dir="auto" class="mt-3 line-clamp-1 text-start font-semibold">{{ $spotlightReview->title }}</p>
                            <p dir="auto" class="mt-1 line-clamp-2 text-start text-sm leading-relaxed text-gray-300">{{ $spotlightReview->body }}</p>
                            <p class="mt-3 truncate text-xs text-gray-400">{{ $spotlightReview->displayName() }} · <bdi class="text-gray-200">{{ $spotlightReview->provider->name }}</bdi></p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Categories, overlapping the bottom of the hero --}}
    @if ($categories->isNotEmpty())
        <section class="relative z-10 mx-auto -mt-14 max-w-6xl px-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-reveal="stagger">
                @foreach ($categories as $category)
                    <a href="{{ route('categories.show', $category) }}" class="group relative flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-float ring-1 ring-black/5 transition duration-200 hover:-translate-y-1 hover:ring-brand-500/40 active:translate-y-0">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-sand text-2xl transition group-hover:bg-brand-50">{{ $category->icon }}</span>
                        <span>
                            <span class="block text-sm font-semibold leading-snug">{{ $category->tr('name') }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500">{{ trans_choice(':count مزود', $category->providers_count) }}</span>
                        </span>
                        <span class="absolute end-4 top-4 text-gray-300 opacity-0 transition group-hover:text-brand-600 group-hover:opacity-100 rtl:rotate-180" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($sections['account_check'])
        <section class="mx-auto mt-16 max-w-6xl px-4" data-reveal>
            @include('home._account-check')
        </section>
    @endif

    {{-- Top rated --}}
    <section class="mx-auto mt-24 max-w-6xl px-4">
        <x-section-heading :eyebrow="__('اختيارات المتداولين')" :title="__('الأعلى تقييماً')"
                           :href="route('providers.index')" :link="__('عرض الكل')" />
        @if ($featured->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-sand text-gray-400">
                    <x-ui-icon name="star" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-semibold">{{ __('لا يوجد مقدمو خدمة بعد.') }}</p>
                <a href="{{ route('submissions.create') }}" class="mt-3 inline-block text-sm font-semibold text-brand-600 hover:underline">{{ __('أضف مقدم خدمة') }}</a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-reveal="stagger">
                @foreach ($featured as $provider)
                    <x-provider-card :provider="$provider" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($alerts && ($alerts['impersonators']->isNotEmpty() || $alerts['flagged']->isNotEmpty()))
        @include('home._alerts')
    @endif

    {{-- Latest reviews: a masonry wall, so short and long reviews sit naturally --}}
    @if ($latestReviews->isNotEmpty())
        <section class="mx-auto mt-24 max-w-6xl px-4">
            <x-section-heading :eyebrow="__('من المجتمع')" :title="__('أحدث التقييمات')"
                               :intro="__('تجارب منشورة من حسابات مؤكدة، كما كتبها أصحابها.')" />
            <div class="gap-4 md:columns-2 lg:columns-3" data-reveal="stagger">
                @foreach ($latestReviews as $review)
                    <article class="mb-4 break-inside-avoid rounded-2xl bg-white p-5 shadow-float ring-1 ring-black/5">
                        <div class="flex items-center justify-between gap-3">
                            <x-stars :rating="$review->rating" size="sm" />
                            <time datetime="{{ $review->created_at->toIso8601String() }}" class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</time>
                        </div>
                        <h3 dir="auto" class="mt-3 text-start font-bold leading-snug">{{ $review->title }}</h3>
                        <p dir="auto" class="mt-1.5 line-clamp-5 text-start text-sm leading-relaxed text-gray-600">{{ $review->body }}</p>
                        <div class="mt-4 flex items-center gap-3 border-t border-gray-100 pt-4">
                            <x-avatar :provider="$review->provider" size="h-9 w-9 text-xs" />
                            <div class="min-w-0 flex-1 text-xs">
                                <a href="{{ route('providers.show', $review->provider) }}" title="{{ $review->provider->name }}" class="block truncate font-semibold text-ink hover:text-brand-700"><bdi>{{ $review->provider->name }}</bdi></a>
                                <span class="block truncate text-gray-500">{{ $review->displayName() }}</span>
                            </div>
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
    <section class="mx-auto mt-24 max-w-6xl px-4" data-reveal>
        <div class="hero-surface rounded-3xl px-6 py-12 text-white md:px-12 md:py-14">
            <div class="flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
                <div class="max-w-xl">
                    <h2 class="text-2xl font-bold tracking-tight text-balance md:text-3xl">{{ __('تقدّم خدمة توصيات أو نسخ صفقات؟') }}</h2>
                    <p class="mt-3 text-pretty leading-relaxed text-gray-300">{{ __('أرسل لنا بياناتك وروابطك وسنضيفك إلى المنصة بعد المراجعة.') }}</p>
                </div>
                <a href="{{ route('submissions.create') }}" class="btn-primary shrink-0 px-7 py-3.5 text-base shadow-lg shadow-brand-500/25 active:scale-[.98]">
                    {{ __('أرسل بياناتك') }}
                    <span class="inline-block rtl:rotate-180" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>
</x-layouts.app>
