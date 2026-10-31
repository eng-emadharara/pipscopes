{{-- Confirmed impersonators and channels with serious warning signs (see App\Support\RedFlags) --}}
<section class="mx-auto mt-14 max-w-6xl px-4">
    <div class="mb-5">
        <h2 class="flex items-center gap-2 text-2xl font-bold">
            <x-ui-icon name="alert" class="h-6 w-6 text-red-600" />
            {{ \App\Support\HomeSections::title($section) }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('حسابات تنتحل صفة قنوات معروفة، وقنوات أشار عدد ملحوظ من مقيّميها إلى مؤشرات خطيرة.') }}</p>
    </div>

    {{-- grid-cols-1 (minmax(0, 1fr)): without it the column grows to the longest untruncated line and widens the page on phones --}}
    <div class="grid grid-cols-1 gap-4 {{ $alerts['impersonators']->isNotEmpty() && $alerts['flagged']->isNotEmpty() ? 'lg:grid-cols-2' : '' }}">
        @if ($alerts['impersonators']->isNotEmpty())
            <div class="card p-5">
                <h3 class="mb-3 font-bold">{{ __('أحدث الحسابات المنتحلة') }}</h3>
                <ul class="space-y-2">
                    @foreach ($alerts['impersonators'] as $fake)
                        {{-- Deliberately not a link to the fake account: we never send traffic to it --}}
                        <li class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50/60 px-3 py-2 text-sm">
                            <x-ui-icon :name="$fake->icon()" class="h-4 w-4 shrink-0 text-red-600" />
                            <div class="min-w-0 flex-1">
                                <span class="block truncate font-mono font-semibold text-red-700 line-through decoration-red-300" dir="ltr">{{ $fake->handle }}</span>
                                <span class="block truncate text-xs text-gray-600">
                                    {{ __('ينتحل صفة') }}
                                    <a href="{{ route('providers.show', $fake->provider) }}#impersonators" class="font-semibold text-ink hover:underline"><bdi>{{ $fake->provider->name }}</bdi></a>
                                </span>
                            </div>
                            <span class="shrink-0 text-xs text-gray-500">{{ $fake->platformLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($alerts['flagged']->isNotEmpty())
            <div class="card p-5">
                <h3 class="mb-3 font-bold">{{ __('قنوات عليها مؤشرات خطيرة') }}</h3>
                <ul class="space-y-2">
                    @foreach ($alerts['flagged'] as $row)
                        <li>
                            <a href="{{ route('providers.show', $row['provider']) }}" class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2 text-sm transition hover:border-amber-300">
                                <x-avatar :provider="$row['provider']" size="h-9 w-9 text-sm" />
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold"><bdi>{{ $row['provider']->name }}</bdi></span>
                                    <span class="block truncate text-xs text-amber-900">{{ collect($row['cons'])->map(fn ($c) => __("trust.cons.$c"))->implode('، ') }}</span>
                                </div>
                                <span class="shrink-0 text-end text-xs text-gray-600">
                                    {{ __(':percent% من المقيّمين', ['percent' => round($row['share'] * 100)]) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-gray-500">{{ __('بناءً على تجارب المقيّمين وليس حكماً نهائياً. اقرأ التقييمات وردود القناة قبل أن تقرر.') }}</p>
            </div>
        @endif
    </div>
</section>
