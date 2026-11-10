<x-layouts.app>
    <x-structured-data :items="\App\Support\StructuredData::site()" />

    {{-- Hero: search, popular channels and numbers --}}
    @php
        // A zero would read as a weakness, so the number shows once there is something to show
        $showImpersonators = $stats['impersonators'] > 0;
        $reviewsCount = \App\Models\Review::publicCount($stats['reviews']);
        // Until the reviews pass 100, the subscribers of the listed channels stand in for them
        $subscribers = $reviewsCount ? null : \App\Models\Provider::publicSubscribers($stats['subscribers']);
        $statColumns = 1 + (int) (bool) ($reviewsCount ?: $subscribers) + (int) $showImpersonators;
    @endphp
    {{-- The background is a layer of its own: clipping or isolating the section itself would trap the search suggestions under the category cards --}}
    <section class="relative bg-ink text-white">
        <div class="hero-bg absolute inset-0 isolate overflow-hidden" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-12 text-center md:pb-20 md:pt-16">
            {{-- While reviews are still being gathered, the page asks for them rather than promising them --}}
            <h1 class="text-3xl font-bold leading-tight md:text-5xl">{{ __('جرّبت قناة توصيات؟ شاركنا تجربتك.') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-gray-300">{{ __('تجربتك الحقيقية، جيدة كانت أو سيئة، تحمي متداولاً آخر قبل أن يدفع اشتراكه.') }}</p>

            <form action="{{ route('providers.index') }}" method="GET" class="relative z-20 mx-auto mt-8 flex max-w-xl items-center rounded-full bg-white p-1.5 shadow-lg shadow-black/30 ring-1 ring-white/10">
                <x-ui-icon name="search" class="mx-3 h-5 w-5 shrink-0 text-gray-400" />
                <input type="search" name="q" data-autocomplete="{{ route('search.suggest') }}" placeholder="{{ __('ابحث عن القناة التي جرّبتها…') }}" class="min-w-0 flex-1 bg-transparent py-2 text-sm text-ink placeholder-gray-400 focus:outline-none" aria-label="{{ __('بحث') }}">
                <button class="btn-primary shrink-0">{{ __('بحث') }}</button>
            </form>

            {{-- Not listed yet? Adding it takes only the Telegram link --}}
            <p class="mx-auto mt-4 max-w-xl text-sm text-gray-300" data-hero-add>
                {{ __('ما لقيت قناتك؟') }}
                <a href="{{ route('submissions.create') }}" class="font-semibold text-brand-500 underline-offset-4 hover:text-brand-100 hover:underline"
                   data-track="add_channel_click" data-track-params='{"location":"hero_search"}'>{{ __('أضفها برابط التيلجرام فقط') }} <span aria-hidden="true">←</span></a>
            </p>

            {{-- The most reviewed channels, one tap away; always one line (phones keep the first two).
                 Hidden for now (config trust.home_popular) until there are enough channels and reviews --}}
            @if (config('trust.home_popular') && $popular->isNotEmpty())
                <div class="mx-auto mt-4 flex max-w-xl items-center justify-center gap-2 text-xs">
                    <span class="shrink-0 text-gray-400">{{ __('شائع:') }}</span>
                    @foreach ($popular as $item)
                        <a href="{{ route('providers.show', $item) }}" data-track="hero_popular_click" data-track-params='@json(['provider' => $item->slug])'
                           @class(['min-w-0 truncate rounded-full border border-white/15 bg-white/5 px-3 py-1 text-gray-200 transition hover:border-brand-500 hover:bg-brand-500/10 hover:text-white', 'max-sm:hidden' => $loop->index >= 2])><bdi>{{ $item->name }}</bdi></a>
                    @endforeach
                </div>
            @endif

            {{-- The invitation to contribute, as soon as they arrive --}}
            @if (\App\Support\ReviewInvite::available())
                <div class="mx-auto mt-6 max-w-xl" data-hero-invite>
                    <div class="flex flex-col justify-center gap-2 sm:flex-row">
                        <a href="{{ route('providers.index') }}" class="btn-primary px-6 py-2.5" data-open-dialog="review-picker"
                           data-track="review_invite_click" data-track-params='{"location":"hero"}'>
                            <x-ui-icon name="star" class="h-4 w-4 shrink-0" /> {{ __('قيّم تجربتك') }}
                        </a>
                        {{-- Adding a channel is offered right under the search, so this one browses (when browsing is open) --}}
                        @unless (\App\Support\Launch::appliesToVisitor())
                            <a href="{{ route('providers.index') }}" class="btn bg-white px-6 py-2.5 text-ink hover:bg-gray-100" data-hero-browse
                               data-track="browse_click" data-track-params='{"location":"hero"}'>
                                <x-ui-icon name="search" class="h-4 w-4 shrink-0" /> {{ __('تصفح جميع القنوات') }}
                            </a>
                        @endunless
                    </div>
                </div>
            @endif

            {{-- divide-x is logical in Tailwind 4, so it needs no RTL reverse. Each number comes first (order-first) so they line up even when a label wraps --}}
            <dl @class(['mx-auto mt-8 grid divide-x divide-white/10', [1 => 'max-w-40 grid-cols-1', 2 => 'max-w-sm grid-cols-2', 3 => 'max-w-xl grid-cols-3'][$statColumns]])>
                <div class="flex flex-col items-center gap-1 px-2">
                    <dt class="text-xs text-gray-400 md:text-sm">{{ __('مقدم خدمة') }}</dt>
                    <dd class="order-first text-xl font-bold tabular-nums md:text-2xl">{{ number_format($stats['providers']) }}</dd>
                </div>
                @if ($reviewsCount)
                    <div class="flex flex-col items-center gap-1 px-2" data-reviews-count>
                        <dt class="text-xs text-gray-400 md:text-sm">{{ __('تقييم') }}</dt>
                        <dd class="order-first text-xl font-bold tabular-nums md:text-2xl" dir="ltr">{{ $reviewsCount }}</dd>
                    </div>
                @elseif ($subscribers)
                    <div class="flex flex-col items-center gap-1 px-2" data-subscribers-count>
                        <dt class="text-xs leading-snug text-balance text-gray-400 md:text-sm">{{ __('مشترك في القنوات المدرجة') }}</dt>
                        <dd class="order-first text-xl font-bold tabular-nums md:text-2xl">{{ $subscribers }}</dd>
                    </div>
                @endif
                {{-- What sets us apart: impersonators caught; links to the account checker when it's on --}}
                @php($checker = collect($layout)->contains('key', 'account_check'))
                @if ($showImpersonators)
                <div class="flex flex-col items-center gap-1 px-2">
                    <dt class="text-xs leading-snug text-balance text-gray-400 md:text-sm">
                        <x-ui-icon name="shield" class="-mt-0.5 me-0.5 inline h-3.5 w-3.5 text-amber-400" />
                        @if ($checker)
                            <a href="#account-check" class="underline decoration-white/20 underline-offset-4 hover:text-white hover:decoration-white">{{ __('حساب منتحل تم كشفه') }}</a>
                        @else
                            {{ __('حساب منتحل تم كشفه') }}
                        @endif
                    </dt>
                    <dd class="order-first text-xl font-bold tabular-nums text-amber-400 md:text-2xl">{{ number_format($stats['impersonators']) }}</dd>
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
