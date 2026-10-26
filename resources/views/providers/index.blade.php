<x-layouts.app :title="$category?->tr('name') ?? __('تصفح مقدمي الخدمة')">
    <section class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8">
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
                <p class="mt-2 text-gray-600">{{ $category->tr('description') }}</p>
            @endif
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[260px_1fr]">
        {{-- Filters --}}
        <aside>
            <form method="GET" action="{{ $category ? route('categories.show', $category) : route('providers.index') }}" class="card space-y-5 p-5 lg:sticky lg:top-4">
                <div data-autocomplete-box>
                    <label class="label" for="q">{{ __('بحث') }}</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="{{ __('اسم أو رابط…') }}" data-autocomplete="{{ route('search.suggest') }}">
                </div>

                @unless ($category)
                    <div>
                        <label class="label" for="category">{{ __('التصنيف') }}</label>
                        <select id="category" name="category" class="input">
                            <option value="">{{ __('الكل') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->tr('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless

                @if ($markets->isNotEmpty())
                    <fieldset>
                        <legend class="label">{{ __('الأسواق') }}</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($markets as $market)
                                <label class="chip chip-pro px-2.5 py-1 text-xs">
                                    <input type="checkbox" name="markets[]" value="{{ $market->slug }}" class="sr-only" @checked(in_array($market->slug, $selectedMarkets, true))>
                                    {{ $market->icon }} {{ $market->tr('name') }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div>
                    <span class="label">{{ __('أقل تقييم') }}</span>
                    <div class="space-y-1.5 text-sm">
                        @foreach (['' => __('أي تقييم'), '3' => __(':n+ نجوم', ['n' => 3]), '4' => __(':n+ نجوم', ['n' => 4]), '4.5' => __(':n+ نجوم', ['n' => 4.5])] as $value => $label)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="min_rating" value="{{ $value }}" @checked((string) request('min_rating', '') === (string) $value) class="accent-brand-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified')) class="accent-brand-500">
                    {{ __('الموثّقون فقط') }}
                </label>

                <div>
                    <label class="label" for="sort">{{ __('الترتيب') }}</label>
                    <select id="sort" name="sort" class="input">
                        <option value="">{{ __('الأعلى تقييماً') }}</option>
                        <option value="reviews" @selected(request('sort') === 'reviews')>{{ __('الأكثر تقييمات') }}</option>
                        <option value="newest" @selected(request('sort') === 'newest')>{{ __('الأحدث إضافة') }}</option>
                        <option value="lowest" @selected(request('sort') === 'lowest')>{{ __('الأقل تقييماً') }}</option>
                    </select>
                </div>

                {{-- Keep the chosen page size when filters change (the page itself restarts at 1) --}}
                @if ($perPage !== \App\Http\Controllers\ProviderController::PER_PAGE_OPTIONS[0])
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                @endif

                <button class="btn-primary w-full">{{ __('تطبيق') }}</button>
                @if (request()->hasAny(['q', 'category', 'markets', 'min_rating', 'verified', 'sort']))
                    <a href="{{ $category ? route('categories.show', $category) : route('providers.index') }}" class="block text-center text-sm text-gray-500 hover:underline">{{ __('مسح الفلاتر') }}</a>
                @endif
            </form>
        </aside>

        {{-- Results --}}
        <div>
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

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
                <p>
                    @if ($providers->total() > $providers->count())
                        {{ __('عرض :from–:to من :total', ['from' => $providers->firstItem(), 'to' => $providers->lastItem(), 'total' => $providers->total()]) }}
                    @else
                        {{ trans_choice(':count نتيجة', $providers->total()) }}
                    @endif
                </p>
                @if ($providers->total() > \App\Http\Controllers\ProviderController::PER_PAGE_OPTIONS[0])
                    <form method="GET" class="flex items-center gap-2">
                        @foreach (request()->except(['per_page', 'page']) as $name => $value)
                            @foreach ((array) $value as $item)
                                <input type="hidden" name="{{ is_array($value) ? $name.'[]' : $name }}" value="{{ $item }}">
                            @endforeach
                        @endforeach
                        <label for="per-page">{{ __('لكل صفحة') }}</label>
                        <select id="per-page" name="per_page" class="input w-auto py-1.5" onchange="this.form.submit()">
                            @foreach (\App\Http\Controllers\ProviderController::PER_PAGE_OPTIONS as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="btn-outline py-1.5">{{ __('تطبيق') }}</button></noscript>
                    </form>
                @endif
            </div>
            @if ($providers->isEmpty())
                <div class="card p-10 text-center">
                    <p class="text-gray-600">{{ __('لم نجد نتائج مطابقة.') }}</p>
                    <a href="{{ route('submissions.create') }}" class="btn-outline mt-4">{{ __('اقترح إضافة مقدم خدمة') }}</a>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($providers as $provider)
                        <x-provider-card :provider="$provider" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $providers->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>
