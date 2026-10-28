{{-- Browse page: a categories strip, one sticky toolbar (search, filters, sort), then the results.
     Every change applies at once (resources/js/provider-filters.js swaps the live regions in place);
     without JS the form still submits. On phones the filters open as a bottom sheet. --}}
@php
    $filterCount = count($activeFilters);
    $ratingOptions = ['' => __('أي تقييم'), '3' => '3+', '4' => '4+', '4.5' => '4.5+'];
    $sortOptions = ['' => __('الأعلى تقييماً'), 'reviews' => __('الأكثر تقييمات'), 'newest' => __('الأحدث إضافة'), 'lowest' => __('الأقل تقييماً')];
    $pill = 'inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl border px-3.5 text-sm font-semibold transition';
    $pillIdle = 'border-gray-200 bg-white hover:border-gray-400';
    $pillOn = 'border-ink bg-ink text-white';
    $popoverBody = 'lg:absolute lg:start-0 lg:top-full lg:z-30 lg:mt-2 lg:hidden lg:rounded-2xl lg:border lg:border-gray-200 lg:bg-white lg:p-4 lg:shadow-xl lg:group-data-[open]:block';
@endphp
<x-layouts.app :title="$category?->tr('name') ?? __('تصفح مقدمي الخدمة')">
    <section class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 pt-6 md:pt-8" data-live-region="heading">
            <h1 class="text-2xl font-bold md:text-3xl">
                @if ($category)
                    {{ $category->icon }} {{ $category->tr('name') }}
                @elseif (request('q'))
                    {{ __('نتائج البحث عن «:q»', ['q' => request('q')]) }}
                @else
                    {{ __('جميع مقدمي الخدمة') }}
                @endif
            </h1>
            @if ($category?->tr('description'))
                <p class="mt-1 text-sm text-gray-600">{{ $category->tr('description') }}</p>
            @endif
        </div>

        {{-- What does it offer: tabs on one line; it scrolls sideways instead of wrapping --}}
        <div class="relative mx-auto max-w-6xl" data-tab-strip>
            {{-- "relative": the tabs' hidden inputs are absolutely placed and would otherwise widen the page --}}
            <div class="relative flex gap-0.5 overflow-x-auto px-2 pt-3 lg:gap-1 lg:px-4 lg:pt-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="radiogroup" aria-label="{{ __('ماذا تقدم؟') }}" data-live-region="f-categories" data-strip-scroller>
                <label class="flex shrink-0 cursor-pointer flex-col items-center gap-0.5 border-b-2 border-transparent px-2.5 pb-2.5 text-gray-500 lg:gap-1 lg:px-4 lg:pb-3 transition hover:border-gray-300 hover:text-ink has-[:checked]:border-ink has-[:checked]:text-ink"
                       title="{{ trans_choice(':count نتيجة متوفرة', $allCategoriesCount, ['count' => number_format($allCategoriesCount)]) }}">
                    <input type="radio" name="categories[]" value="" form="provider-filters" class="sr-only" @checked(! $selectedCategories)>
                    <span class="text-lg leading-none lg:text-2xl">🗂️</span>
                    <span class="whitespace-nowrap text-xs font-semibold lg:text-sm">{{ __('الكل') }}</span>
                </label>
                @foreach ($categories as $cat)
                    @php($on = in_array($cat->slug, $selectedCategories, true))
                    <label @class([
                        'flex shrink-0 cursor-pointer flex-col items-center gap-0.5 border-b-2 border-transparent px-2.5 pb-2.5 text-gray-500 lg:gap-1 lg:px-4 lg:pb-3 transition hover:border-gray-300 hover:text-ink has-[:checked]:border-ink has-[:checked]:text-ink',
                        'pointer-events-none opacity-35' => ! $on && ! $cat->providers_count,
                    ]) title="{{ trans_choice(':count نتيجة متوفرة', $cat->providers_count, ['count' => number_format($cat->providers_count)]) }}">
                        <input type="radio" name="categories[]" value="{{ $cat->slug }}" form="provider-filters" class="sr-only" @checked($on) @disabled(! $on && ! $cat->providers_count)>
                        <span class="text-lg leading-none lg:text-2xl">{{ $cat->icon }}</span>
                        <span class="whitespace-nowrap text-xs font-semibold lg:text-sm">{{ $cat->tr('name') }}</span>
                    </label>
                @endforeach
            </div>
            {{-- Desktop: arrows when the tabs don't all fit (shown by the script) --}}
            <button type="button" class="absolute start-0 top-4 hidden h-12 w-10 items-center justify-center bg-gradient-to-l from-white via-white to-transparent text-xl text-gray-500 hover:text-ink rtl:bg-gradient-to-r" data-strip-scroll="-1" aria-label="{{ __('السابق') }}"><span class="rtl:rotate-180">‹</span></button>
            <button type="button" class="absolute end-0 top-4 hidden h-12 w-10 items-center justify-center bg-gradient-to-r from-white via-white to-transparent text-xl text-gray-500 hover:text-ink rtl:bg-gradient-to-l" data-strip-scroll="1" aria-label="{{ __('التالي') }}"><span class="rtl:rotate-180">›</span></button>
        </div>
    </section>

    {{-- One toolbar for every control; it stays on screen while scrolling. No backdrop-blur here:
         it would make the phones' filters sheet (position: fixed, inside) open within the toolbar. --}}
    <div class="sticky top-0 z-30 border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3">
            <label class="relative min-w-0 flex-1 lg:max-w-sm">
                <span class="sr-only">{{ __('بحث') }}</span>
                <x-ui-icon name="search" class="pointer-events-none absolute start-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                <input type="search" name="q" form="provider-filters" value="{{ request('q') }}" autocomplete="off" enterkeyhint="search"
                       class="input h-11 ps-10" placeholder="{{ __('ابحث عن قناة…') }}" data-filter-search>
            </label>

            <button type="button" class="{{ $pill }} {{ $pillIdle }} relative lg:hidden" data-open-filters aria-controls="filters-panel">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                {{ __('الفلاتر') }}
                <span class="grid h-5 min-w-5 place-items-center rounded-full bg-brand-500 px-1 text-xs text-white {{ $filterCount ? '' : 'hidden' }}" data-live-region="filter-badge">{{ $filterCount }}</span>
            </button>

            {{-- The filters. Desktop: dropdown buttons in this toolbar. Phones: a bottom sheet.
                 One set of fields serves both; counts show what each option would give. --}}
            <div id="filters-panel" class="fixed inset-0 z-50 hidden lg:static lg:z-auto lg:contents" data-filters-panel>
                <div class="absolute inset-0 bg-black/40 lg:hidden" data-close-filters></div>

                <form id="provider-filters" method="GET" action="{{ route('providers.index') }}" data-live-filters
                      class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col rounded-t-3xl bg-white shadow-2xl lg:static lg:flex-1 lg:flex-row lg:items-center lg:rounded-none lg:bg-transparent lg:shadow-none"
                      aria-label="{{ __('الفلاتر') }}">

                    {{-- Phones: grab handle and title --}}
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 pb-3 pt-2 lg:hidden">
                        <div class="absolute inset-x-0 top-2 mx-auto h-1 w-10 rounded-full bg-gray-300"></div>
                        <h2 class="mt-3 text-lg font-bold">{{ __('الفلاتر') }}</h2>
                        <button type="button" class="mt-3 grid h-9 w-9 place-items-center rounded-full hover:bg-gray-100" data-close-filters aria-label="{{ __('إغلاق') }}">&times;</button>
                    </div>

                    <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5 lg:flex lg:items-center lg:gap-2 lg:space-y-0 lg:overflow-visible lg:p-0">
                        {{-- Phones: the categories again, as chips. They pick the strip's tab (no field of their own) --}}
                        <div class="lg:hidden">
                            <h3 class="mb-2 text-sm font-bold">{{ __('ماذا تقدم؟') }}</h3>
                            <div class="flex flex-wrap gap-2" data-live-region="f-categories-sheet">
                                @foreach ([['', '🗂️', __('الكل'), $allCategoriesCount], ...$categories->map(fn ($c) => [$c->slug, $c->icon, $c->tr('name'), $c->providers_count])] as [$slug, $icon, $name, $count])
                                    @php($on = $slug === '' ? ! $selectedCategories : in_array($slug, $selectedCategories, true))
                                    <button type="button" data-pick-category="{{ $slug }}" aria-pressed="{{ $on ? 'true' : 'false' }}" @disabled(! $on && ! $count)
                                            class="chip aria-pressed:border-brand-500 aria-pressed:bg-brand-50 aria-pressed:text-brand-700 disabled:pointer-events-none disabled:opacity-40">
                                        {{ $icon }} {{ $name }} <span class="text-xs text-gray-400">{{ number_format($count) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if ($markets->isNotEmpty())
                            <div class="group relative" data-popover>
                                <button type="button" data-popover-toggle aria-expanded="false" data-live-region="f-markets-label"
                                        @class([$pill, 'max-lg:hidden', $pillOn => $selectedMarkets, $pillIdle => ! $selectedMarkets])>
                                    {{ __('السوق') }}@if ($selectedMarkets) <span class="rounded-full bg-white/20 px-1.5 text-xs">{{ count($selectedMarkets) }}</span>@endif
                                    <span class="text-xs opacity-60">▾</span>
                                </button>
                                <fieldset class="{{ $popoverBody }} lg:w-80">
                                    <legend class="mb-2 text-sm font-bold">{{ __('الأسواق') }}</legend>
                                    <div class="flex flex-wrap gap-2" data-live-region="f-markets">
                                        @foreach ($markets as $market)
                                            @php($on = in_array($market->slug, $selectedMarkets, true))
                                            <label @class(['chip chip-pro', 'pointer-events-none opacity-40' => ! $on && ! $market->providers_count])>
                                                <input type="checkbox" name="markets[]" value="{{ $market->slug }}" class="sr-only" @checked($on) @disabled(! $on && ! $market->providers_count)>
                                                {{ $market->icon }} {{ $market->tr('name') }}
                                                <span class="text-xs text-gray-400">{{ number_format($market->providers_count) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>
                        @endif

                        {{-- Rating: a minimum score (rows with the stars it means) and a minimum number of reviews --}}
                        @php($ratingActive = request()->filled('min_rating') || $minReviews)
                        <div class="group relative" data-popover>
                            <button type="button" data-popover-toggle aria-expanded="false" data-live-region="f-rating-label"
                                    @class([$pill, 'max-lg:hidden', $pillOn => $ratingActive, $pillIdle => ! $ratingActive])>
                                <span class="text-amber-400">★</span>
                                {{ request()->filled('min_rating') ? ($ratingOptions[request('min_rating')] ?? __('التقييم')) : __('التقييم') }}@if ($minReviews)<span class="opacity-70"> · {{ __(':n+ تقييمات', ['n' => $minReviews]) }}</span>@endif
                                <span class="text-xs opacity-60">▾</span>
                            </button>
                            <div class="{{ $popoverBody }} space-y-5 lg:w-80" data-live-region="f-rating">
                                <fieldset>
                                    <legend class="mb-1.5 text-sm font-bold">{{ __('أقل تقييم') }}</legend>
                                    <div class="space-y-0.5">
                                        @foreach ($ratingOptions as $value => $label)
                                            @php($on = (string) request('min_rating', '') === (string) $value)
                                            @php($empty = ! $on && ! $ratingCounts[$value])
                                            <label @class(['flex cursor-pointer items-center gap-3 rounded-xl px-2.5 py-2 text-sm transition hover:bg-gray-50 has-[:checked]:bg-brand-50', 'pointer-events-none opacity-40' => $empty])>
                                                <input type="radio" name="min_rating" value="{{ $value }}" class="peer sr-only" @checked($on) @disabled($empty)>
                                                {{-- Radio dot --}}
                                                <span class="grid h-4.5 w-4.5 shrink-0 place-items-center rounded-full border-2 border-gray-300 peer-checked:border-brand-500 peer-checked:[&>span]:block">
                                                    <span class="hidden h-2 w-2 rounded-full bg-brand-500"></span>
                                                </span>
                                                @if ($value === '')
                                                    <span class="flex-1 font-semibold">{{ __('أي تقييم') }}</span>
                                                @else
                                                    <x-stars :rating="(float) $value" size="sm" />
                                                    <span class="flex-1">
                                                        <span class="font-semibold tabular-nums">{{ $label }}</span>
                                                        <span class="text-gray-500">{{ \App\Models\Provider::ratingLabel((float) $value) }}{{ $value !== '4.5' ? ' '.__('فأعلى') : '' }}</span>
                                                    </span>
                                                @endif
                                                <span class="text-xs tabular-nums text-gray-400">{{ number_format($ratingCounts[$value]) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>

                                <fieldset>
                                    <legend class="mb-1.5 text-sm font-bold">{{ __('عدد التقييمات') }}</legend>
                                    <p class="mb-2 text-xs text-gray-500">{{ __('التقييم المبني على تجارب أكثر أدق.') }}</p>
                                    <div class="grid grid-cols-4 gap-1 rounded-xl bg-gray-100 p-1 text-sm">
                                        @foreach ($reviewCounts as $value => $count)
                                            @php($on = (string) ($minReviews ?? '') === (string) $value)
                                            <label @class(['cursor-pointer rounded-lg py-1.5 text-center font-medium text-gray-600 transition has-[:checked]:bg-white has-[:checked]:text-ink has-[:checked]:shadow-sm', 'pointer-events-none opacity-40' => ! $on && ! $count])>
                                                <input type="radio" name="min_reviews" value="{{ $value }}" class="sr-only" @checked($on) @disabled(! $on && ! $count)>
                                                <span class="block">{{ $value === '' ? __('الكل') : $value.'+' }}</span>
                                                <span class="block text-xs text-gray-400">{{ number_format($count) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>
                        </div>

                        <div data-live-region="f-verified">
                            {{-- A switch row in the sheet, a toggle button in the toolbar --}}
                            <label class="flex cursor-pointer items-center justify-between gap-3 text-sm font-bold transition lg:inline-flex lg:h-11 lg:justify-start lg:gap-1.5 lg:rounded-xl lg:border lg:border-gray-200 lg:bg-white lg:px-3.5 lg:font-semibold lg:hover:border-gray-400 lg:has-[:checked]:border-ink lg:has-[:checked]:bg-ink lg:has-[:checked]:text-white">
                                <span><span class="hidden lg:inline">✓ </span>{{ __('الموثّقون فقط') }} <span class="text-xs font-normal opacity-60">{{ number_format($verifiedCount) }}</span></span>
                                <span class="relative inline-flex lg:hidden">
                                    <input type="checkbox" name="verified" value="1" class="peer sr-only" @checked(request()->boolean('verified'))>
                                    <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-brand-500"></span>
                                    <span class="absolute start-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Phones: always-visible actions; the count follows the filters live --}}
                    <div class="flex gap-3 border-t border-gray-100 bg-white p-4 lg:hidden">
                        <a href="{{ route('providers.index') }}" class="btn-outline flex-1" data-clear-all>{{ __('مسح الكل') }}</a>
                        <button type="button" class="btn-primary flex-[2]" data-close-filters>
                            <span data-live-region="show-count">{{ trans_choice('عرض :count نتيجة', $providers->total(), ['count' => number_format($providers->total())]) }}</span>
                        </button>
                    </div>

                    {{-- Without JS the filters need a button --}}
                    <noscript><div class="p-4 lg:p-0 lg:ps-2"><button class="btn-primary w-full lg:w-auto">{{ __('تطبيق') }}</button></div></noscript>
                </form>
            </div>
        </div>
    </div>

    {{-- Results --}}
    <div class="mx-auto max-w-6xl px-4 py-5 lg:py-6" data-live-region="results">
        @foreach ($impersonatorWarnings as $fake)
            <div class="mb-4 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <x-ui-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <div>
                    <strong>{{ __('تحذير: الحساب :handle على :platform حساب منتحل.', ['handle' => $fake->handle, 'platform' => $fake->platformLabel()]) }}</strong>
                    {{ __('ينتحل «:name» وليس تابعاً له. لا ترسل له أموالاً.', ['name' => $fake->provider->name]) }}
                    <a href="{{ route('providers.show', $fake->provider) }}#official-links" class="font-semibold underline">{{ __('الروابط الرسمية') }}</a>
                </div>
            </div>
        @endforeach

        {{-- One light line: how many, what's applied, and the order --}}
        <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
            <p class="text-gray-600" aria-live="polite">
                <strong class="text-ink">{{ trans_choice(':count نتيجة متوفرة', $providers->total(), ['count' => number_format($providers->total())]) }}</strong>
                @if ($providers->total() > $providers->count())
                    <span class="text-gray-400">· {{ __('عرض :from–:to من :total', ['from' => $providers->firstItem(), 'to' => $providers->lastItem(), 'total' => $providers->total()]) }}</span>
                @endif
            </p>
            @foreach ($activeFilters as $filter)
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-ink hover:bg-gray-200"
                        data-clear-filter data-name="{{ $filter['name'] }}" data-value="{{ $filter['value'] }}" aria-label="{{ __('إزالة :f', ['f' => $filter['label']]) }}">
                    {{ $filter['label'] }} <span class="text-sm leading-none text-gray-500">&times;</span>
                </button>
            @endforeach
            @if ($activeFilters)
                <a href="{{ route('providers.index') }}" class="text-xs font-semibold text-gray-500 hover:text-ink hover:underline" data-clear-all>{{ __('مسح الكل') }}</a>
            @endif

            {{-- Sort: at the end of the results line, phones and desktop alike --}}
            <label class="ms-auto flex items-center gap-2 text-gray-500">
                <span class="max-sm:sr-only">{{ __('الترتيب') }}</span>
                <select id="sort" name="sort" form="provider-filters" class="input h-9 w-auto rounded-lg border-gray-200 py-0 pe-8 text-sm font-semibold text-ink">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('sort', '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($providers->isEmpty())
            <div class="card p-10 text-center">
                <p class="text-gray-600">{{ __('لم نجد نتائج مطابقة.') }}</p>
                @if ($activeFilters)
                    <a href="{{ route('providers.index', request()->only('q')) }}" class="btn-outline mt-4" data-clear-all>{{ __('مسح الفلاتر') }}</a>
                @endif
                <a href="{{ route('submissions.create') }}" class="btn-outline mt-4">{{ __('اقترح إضافة مقدم خدمة') }}</a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-results-grid>
                @foreach ($providers as $provider)
                    <x-provider-card :provider="$provider" />
                @endforeach
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                {{ $providers->links() }}
                @if ($providers->total() > \App\Http\Controllers\ProviderController::PER_PAGE_OPTIONS[0])
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <label for="per-page">{{ __('لكل صفحة') }}</label>
                        <select id="per-page" name="per_page" form="provider-filters" class="input w-auto py-1.5">
                            @foreach (\App\Http\Controllers\ProviderController::PER_PAGE_OPTIONS as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Shown in place of the results while new ones load (resources/js/provider-filters.js) --}}
    <template data-skeleton>
        @foreach (range(1, 8) as $i)
            <div class="card animate-pulse space-y-3 p-4">
                <div class="flex items-center gap-3"><div class="h-12 w-12 rounded-xl bg-gray-200"></div><div class="flex-1 space-y-2"><div class="h-3.5 w-2/3 rounded bg-gray-200"></div><div class="h-3 w-1/3 rounded bg-gray-100"></div></div></div>
                <div class="h-14 rounded-xl bg-gray-100"></div>
                <div class="h-3 w-4/5 rounded bg-gray-100"></div>
            </div>
        @endforeach
    </template>
</x-layouts.app>
