@php
    $avg = (float) $provider->rating_avg;
    $barColor = fn (float $v) => [
        'excellent' => 'bg-rate-excellent', 'great' => 'bg-rate-great', 'average' => 'bg-rate-average',
        'poor' => 'bg-rate-poor', 'bad' => 'bg-rate-bad', 'none' => 'bg-rate-none',
    ][\App\Models\Provider::ratingColor($v)];
    $starBar = [5 => 'bg-rate-excellent', 4 => 'bg-rate-great', 3 => 'bg-rate-average', 2 => 'bg-rate-poor', 1 => 'bg-rate-bad'];
    $distribution = $analysis->distribution();
    $criteria = collect($analysis->criteria())->filter(fn ($c) => $c['avg'] !== null);
    $pros = $analysis->topTags('pros');
    $cons = $analysis->topTags('cons');
    $serviceType = $provider->tr('service_type');
@endphp
<x-layouts.app :title="$provider->name"
    :description="__('تقييمات وآراء المستخدمين حول :name - :service', ['name' => $provider->name, 'service' => $serviceType])"
    :og-image="route('share-images.provider', [app()->getLocale(), $provider])">
    {{-- Owner mode: a management toolbar at the very top, above the channel itself --}}
    @if ($managing)
        <div class="sticky top-0 z-30 bg-sky-800 text-white shadow-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-sm">
                <span class="inline-flex items-center gap-2 font-semibold">
                    <x-ui-icon name="shield" class="h-5 w-5 text-sky-200" />
                    {{ __('أنت تدير هذه القناة') }}
                </span>
                <a href="{{ route('providers.show', [$provider, 'replied' => '0']) }}#reviews"
                   class="rounded-full px-3 py-1 text-xs font-semibold {{ $unanswered ? 'bg-amber-300 text-amber-950 hover:bg-amber-200' : 'bg-white/15 text-sky-50 hover:bg-white/25' }}">
                    {{ trans_choice(':count تقييم بدون رد', $unanswered) }}
                </a>
                <div class="ms-auto flex flex-wrap items-center gap-2">
                    <button type="button" data-open-dialog="owner-edit" class="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-1.5 font-semibold text-sky-900 hover:bg-sky-50">
                        <x-ui-icon name="link" class="h-4 w-4" /> {{ __('تعديل بيانات القناة') }}
                    </button>
                    <form method="POST" action="{{ route('owner.logout') }}">
                        @csrf
                        <button class="rounded-full px-3 py-1.5 font-medium text-sky-100 hover:bg-white/10">{{ __('خروج') }}</button>
                    </form>
                </div>
            </div>
        </div>
        @include('owners._edit-dialog')
    @endif

    {{-- Header: identity on the start side, rating card on the end side, actions under the identity --}}
    @php
        $ratingText = [
            'excellent' => 'text-rate-excellent', 'great' => 'text-[#4d8c0b]', 'average' => 'text-[#a37f00]',
            'poor' => 'text-rate-poor', 'bad' => 'text-rate-bad', 'none' => 'text-gray-500',
        ][\App\Models\Provider::ratingColor($avg)];
    @endphp
    <section class="border-b border-gray-200 bg-white">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 md:grid-cols-[1fr_auto] md:gap-x-10">
            {{-- Identity --}}
            <div class="flex min-w-0 items-start gap-4 md:col-start-1 md:row-start-1">
                <x-avatar :provider="$provider" size="h-16 w-16 text-2xl md:h-24 md:w-24 md:text-4xl" />
                <div class="min-w-0">
                    <nav class="mb-1 text-xs text-gray-500">
                        <a href="{{ route('providers.index') }}" class="hover:underline">{{ __('مقدمو الخدمة') }}</a>
                        @if ($mainCategory = $provider->categories->first())
                            / <a href="{{ route('categories.show', $mainCategory) }}" class="hover:underline">{{ $mainCategory->tr('name') }}</a>
                        @endif
                    </nav>
                    <h1 class="break-words text-2xl font-bold leading-tight md:text-3xl">
                        <bdi>{{ $provider->name }}</bdi>
                        @if ($provider->is_verified)
                            <span class="ms-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-brand-50 px-2 py-0.5 align-middle text-xs font-semibold text-brand-700">
                                <x-ui-icon name="check" class="h-3.5 w-3.5" /> {{ __('موثّق') }}
                            </span>
                        @endif
                    </h1>
                    @if ($handle = $provider->telegramHandle())
                        <a href="{{ $provider->telegram_url }}" target="_blank" rel="nofollow noopener" class="mt-1 inline-flex items-center gap-1 text-sm text-sky-700 hover:underline">
                            <x-ui-icon name="telegram" class="h-4 w-4" /> <span dir="ltr">{{ $handle }}</span>
                        </a>
                    @endif
                    <p class="mt-1 text-gray-600">{{ $serviceType }}</p>
                </div>
            </div>

            {{-- Rating card --}}
            <a href="#reviews" class="block rounded-2xl border border-gray-200 bg-gray-50 p-5 transition hover:border-gray-300 md:col-start-2 md:row-span-2 md:row-start-1 md:min-w-72 md:self-center">
                @if ($provider->reviews_count)
                    <div class="flex items-baseline gap-2">
                        <span class="text-5xl font-bold leading-none tabular-nums">{{ number_format($avg, 1) }}</span>
                        <span class="text-lg text-gray-400">/ 5</span>
                        <span class="ms-auto text-lg font-bold {{ $ratingText }}">{{ \App\Models\Provider::ratingLabel($avg) }}</span>
                    </div>
                    <x-stars :rating="$avg" size="lg" class="mt-3" />
                    <p class="mt-3 text-sm text-gray-500 underline-offset-2 hover:underline">{{ __('بناءً على :count', ['count' => trans_choice(':count تقييم', $provider->reviews_count)]) }}</p>
                @else
                    <x-stars :rating="0" size="lg" />
                    <p class="mt-3 font-semibold">{{ __('لا توجد تقييمات بعد') }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('كن أول من يشارك تجربته مع هذه القناة.') }}</p>
                @endif
            </a>

            {{-- Actions, side by side --}}
            <div class="flex flex-wrap items-center gap-2 md:col-start-1 md:row-start-2 md:ps-28">
                @if ($userReview)
                    <a href="{{ route('reviews.create', $provider) }}" class="btn-outline">
                        <x-ui-icon name="star" class="h-4 w-4" /> {{ __('تعديل تقييمك') }}
                    </a>
                @else
                    <a href="{{ route('reviews.create', $provider) }}" class="btn-primary">
                        <x-ui-icon name="star" class="h-4 w-4" /> {{ __('اكتب تقييماً') }}
                    </a>
                @endif
                @if ($provider->telegram_url)
                    <a href="{{ $provider->telegram_url }}" target="_blank" rel="nofollow noopener" class="btn border border-sky-200 bg-sky-50 text-sky-800 hover:border-sky-400">
                        <x-ui-icon name="telegram" class="h-4 w-4" /> {{ __('قناة التيليجرام') }}
                    </a>
                @endif
                <x-share-buttons :url="$provider->shareUrl()" :text="$provider->shareText()" />
            </div>
        </div>
    </section>

    @if ($impersonators->isNotEmpty())
        <div class="border-b border-red-200 bg-red-50">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm text-red-800">
                <x-ui-icon name="alert" class="h-5 w-5 shrink-0" />
                <strong>{{ trans_choice('تنبيه: يوجد :count حساب ينتحل هذه القناة.', $impersonators->count()) }}</strong>
                <span>{{ __('تعامل فقط مع الروابط الرسمية، ولا ترسل أموالاً لأي حساب يتواصل معك بشكل خاص.') }}</span>
                <a href="#impersonators" class="font-semibold underline">{{ __('عرض الحسابات المنتحلة') }}</a>
            </div>
        </div>
    @endif

    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[1fr_320px]">
        @php
            $hasTrend = \App\Support\TelegramTrend::enabled() && \App\Support\TelegramTrend::drawable($trend = \App\Support\TelegramTrend::lastDays($provider));
            $tabs = array_filter([
                'reviews' => __('التقييمات'),
                'analysis' => __('تحليل التقييم'),
                'telegram' => $hasTrend ? __('أداء القناة على تيليجرام') : null,
            ]);
            // Reviews first; ?tab= opens another one
            $activeTab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'reviews';
        @endphp
        <div class="min-w-0 space-y-4" data-tabs data-tabs-url id="tabs">
            {{-- Tab bar: real links (?tab=…) so it works without JS; resources/js/ui.js switches in place --}}
            <nav class="card flex gap-1 overflow-x-auto p-1.5" role="tablist" aria-label="{{ __('أقسام صفحة القناة') }}">
                @foreach ($tabs as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}#tabs" data-tab="{{ $key }}" role="tab"
                       aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}" aria-controls="{{ $key === 'reviews' ? 'reviews' : 'panel-'.$key }}"
                       class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-ink aria-selected:bg-ink aria-selected:text-white">
                        @if ($key === 'telegram') <x-ui-icon name="telegram" class="h-4 w-4" /> @endif
                        {{ $label }}
                        @if ($key === 'reviews' && $provider->reviews_count)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ number_format($provider->reviews_count) }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            {{-- Rating analysis --}}
            <section class="card p-6 {{ $activeTab === 'analysis' ? '' : 'hidden' }}" data-panel="analysis" id="panel-analysis" role="tabpanel">
                <h2 class="mb-4 text-lg font-bold">{{ __('تحليل التقييم: لماذا هذا التقييم؟') }}</h2>

                <ul class="mb-6 space-y-2 rounded-xl bg-gray-50 p-4 text-sm leading-relaxed">
                    @foreach ($analysis->summary() as $line)
                        <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span><span>{{ $line }}</span></li>
                    @endforeach
                </ul>

                @if ($analysis->total() > 0)
                    <div class="grid gap-8 md:grid-cols-2">
                        {{-- Star distribution --}}
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ __('توزيع النجوم') }}</h3>
                            <div class="space-y-2">
                                @foreach ($distribution as $star => $row)
                                    <a href="{{ request('stars') == $star ? route('providers.show', $provider) : route('providers.show', [$provider, 'stars' => $star]) }}#reviews"
                                       class="flex items-center gap-3 rounded-md text-sm hover:bg-gray-50 {{ request('stars') == $star ? 'font-bold' : '' }}">
                                        <span class="w-16 shrink-0">{{ trans_choice(':count نجوم', $star) }}</span>
                                        <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                            <span class="block h-full rounded-full {{ $starBar[$star] }}" style="width: {{ $row['percent'] }}%"></span>
                                        </span>
                                        <span class="w-10 shrink-0 text-end text-gray-500">{{ $row['percent'] }}%</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        {{-- Criteria --}}
                        @if ($criteria->isNotEmpty())
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ __('التقييم حسب المعايير') }}</h3>
                                <div class="space-y-3">
                                    @foreach ($criteria as $c)
                                        <div>
                                            <div class="mb-1 flex justify-between text-sm">
                                                <span>{{ $c['label'] }}</span>
                                                <span class="font-semibold">{{ number_format($c['avg'], 1) }}</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                                <div class="h-full rounded-full {{ $barColor($c['avg']) }}" style="width: {{ $c['avg'] / 5 * 100 }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($pros || $cons)
                        <div class="mt-8 grid gap-6 md:grid-cols-2">
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-brand-700">👍 {{ __('أسباب الإيجابية') }}</h3>
                                @forelse ($pros as $tag)
                                    <div class="mb-2 flex items-center justify-between rounded-lg bg-brand-50 px-3 py-2 text-sm">
                                        <span>{{ $tag['label'] }}</span>
                                        <span class="text-xs font-semibold text-brand-700">{{ $tag['count'] }} ({{ $tag['percent'] }}%)</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('لا توجد بيانات.') }}</p>
                                @endforelse
                            </div>
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-red-700">👎 {{ __('أسباب السلبية') }}</h3>
                                @forelse ($cons as $tag)
                                    <div class="mb-2 flex items-center justify-between rounded-lg bg-red-50 px-3 py-2 text-sm">
                                        <span>{{ $tag['label'] }}</span>
                                        <span class="text-xs font-semibold text-red-700">{{ $tag['count'] }} ({{ $tag['percent'] }}%)</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('لا توجد بيانات.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endif
            </section>

            @if ($hasTrend)
                <div class="{{ $activeTab === 'telegram' ? '' : 'hidden' }}" data-panel="telegram" id="panel-telegram" role="tabpanel">
                    @include('providers._telegram-trend', ['trend' => $trend])
                </div>
            @endif

            {{-- Reviews --}}
            <section id="reviews" class="scroll-mt-6 {{ $activeTab === 'reviews' ? '' : 'hidden' }}" data-panel="reviews" role="tabpanel">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">
                        {{ __('التقييمات') }}
                        @if (request('stars'))
                            <span class="text-sm font-normal text-gray-500">({{ __(':n نجوم فقط', ['n' => request('stars')]) }} · <a href="{{ route('providers.show', [$provider, 'sort' => request('sort')]) }}#reviews" class="underline">{{ __('إلغاء الفلتر') }}</a>)</span>
                        @endif
                    </h2>
                    @if ($reviews->total() > 1)
                        <form method="GET" action="{{ route('providers.show', $provider) }}#reviews" class="flex items-center gap-2 text-sm">
                            @if (request('stars'))
                                <input type="hidden" name="stars" value="{{ request('stars') }}">
                            @endif
                            <label for="review-sort" class="text-gray-500">{{ __('الترتيب') }}</label>
                            <select id="review-sort" name="sort" class="input w-auto py-1.5" onchange="this.form.submit()">
                                @foreach (__('trust.review_sorts') as $key => $label)
                                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <noscript><button class="btn-outline py-1.5">{{ __('تطبيق') }}</button></noscript>
                        </form>
                    @endif
                </div>

                @if ($userReview && $userReview->status !== \App\Models\Review::STATUS_APPROVED)
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        {{ __('حالة تقييمك:') }} <strong>{{ __('trust.review_statuses.'.$userReview->status) }}</strong>
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse ($reviews as $review)
                        <x-review-card :review="$review" :provider="$provider" :managing="$managing" :voted="in_array($review->id, $votedIds)" :reported="in_array($review->id, $reportedIds)" />
                    @empty
                        <div class="card p-10 text-center">
                            <p class="text-gray-600">{{ request('stars') ? __('لا توجد تقييمات بهذا العدد من النجوم.') : __('لا توجد تقييمات بعد.') }}</p>
                            <a href="{{ route('reviews.create', $provider) }}" class="btn-primary mt-4">{{ __('كن أول من يقيّم') }}</a>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">{{ $reviews->fragment('reviews')->links() }}</div>
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-4">
            <div class="card p-5">
                <h2 class="mb-3 font-bold">{{ __('عن مقدم الخدمة') }}</h2>
                @if ($provider->tr('bio'))
                    <p class="mb-4 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $provider->tr('bio') }}</p>
                @endif
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('نوع الخدمة') }}</dt><dd class="text-end font-medium">{{ $serviceType }}</dd></div>
                    @if ($provider->categories->isNotEmpty())
                        <div class="flex justify-between gap-2">
                            <dt class="shrink-0 text-gray-500">{{ trans_choice('التصنيفات', $provider->categories->count()) }}</dt>
                            <dd class="flex flex-wrap justify-end gap-1">
                                @foreach ($provider->categories as $category)
                                    <a href="{{ route('categories.show', $category) }}" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium hover:bg-gray-200">{{ $category->icon }} {{ $category->tr('name') }}</a>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($provider->markets->isNotEmpty())
                        <div class="flex justify-between gap-2">
                            <dt class="shrink-0 text-gray-500">{{ __('الأسواق') }}</dt>
                            <dd class="flex flex-wrap justify-end gap-1">
                                @foreach ($provider->markets as $market)
                                    <a href="{{ route('providers.index', ['markets' => [$market->slug]]) }}" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium hover:bg-gray-200">{{ $market->icon }} {{ $market->tr('name') }}</a>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($provider->pricingLabel())
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('التسعير') }}</dt><dd class="text-end font-medium">{{ $provider->pricingLabel() }}</dd></div>
                    @endif
                    @if ($provider->telegram_members)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('مشتركو التيليجرام') }}</dt><dd class="font-medium">{{ number_format($provider->telegram_members) }}</dd></div>
                    @endif
                    @if ($avgViews = $provider->telegram_data['avg_views'] ?? null)
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('متوسط مشاهدات المنشور') }}</dt><dd class="font-medium">{{ number_format($avgViews) }}</dd></div>
                    @endif
                    @if ($provider->telegram_status === \App\Services\TelegramChannel::CHANNEL && $provider->telegram_checked_at)
                        <p class="text-end text-xs text-gray-400">{{ __('أرقام تيليجرام محدّثة :time', ['time' => $provider->telegram_checked_at->diffForHumans()]) }}</p>
                    @endif
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('على المنصة منذ') }}</dt><dd class="font-medium">{{ $provider->created_at->translatedFormat('F Y') }}</dd></div>
                </dl>
            </div>

            @if ($links = $provider->officialLinks())
                <div class="card p-5" id="official-links">
                    <h2 class="mb-1 flex items-center gap-2 font-bold">
                        <x-ui-icon name="shield" class="h-5 w-5 text-brand-600" />
                        {{ __('الروابط الرسمية') }}
                    </h2>
                    <p class="mb-3 text-xs text-gray-500">{{ __('هذه هي الحسابات الرسمية الوحيدة. أي حساب آخر بنفس الاسم أو الصورة ليس تابعاً لمقدم الخدمة.') }}</p>
                    <ul class="space-y-2">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="nofollow noopener" class="flex items-center gap-3 rounded-lg border border-brand-100 bg-brand-50/40 px-3 py-2 text-sm hover:border-brand-500">
                                    <x-ui-icon :name="$link['icon']" class="h-5 w-5 shrink-0 text-gray-700" />
                                    <span class="shrink-0 font-medium">{{ $link['label'] }}</span>
                                    <span class="ms-auto truncate text-xs text-gray-500" dir="ltr">{{ \Illuminate\Support\Str::after($link['url'], '://') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-impersonators :provider="$provider" :impersonators="$impersonators" />
        </aside>
    </div>
</x-layouts.app>
