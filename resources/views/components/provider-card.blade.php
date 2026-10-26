@props(['provider'])
@php
    $rated = $provider->reviews_count > 0;
    $tone = \App\Models\Provider::ratingColor((float) $provider->rating_avg);
    $scoreClass = [
        'excellent' => 'bg-rate-excellent text-white', 'great' => 'bg-rate-great text-white', 'average' => 'bg-rate-average text-ink',
        'poor' => 'bg-rate-poor text-white', 'bad' => 'bg-rate-bad text-white', 'none' => 'bg-gray-100 text-gray-400',
    ][$rated ? $tone : 'none'];
    $categories = $provider->categories;
@endphp
<a href="{{ route('providers.show', $provider) }}" class="card group relative flex h-full flex-col overflow-hidden transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md">
    @if ($provider->is_featured)
        <span class="absolute end-0 top-0 rounded-es-xl bg-amber-400 px-2.5 py-0.5 text-[11px] font-bold text-ink">{{ __('مميز') }}</span>
    @endif

    <div class="flex flex-1 flex-col gap-3 p-4">
        {{-- Identity --}}
        <div class="flex items-center gap-3">
            <x-avatar :provider="$provider" size="h-12 w-12 text-lg" />
            <div class="min-w-0 flex-1">
                {{-- Long names wrap to two lines instead of being cut; the full name shows on hover --}}
                <h3 title="{{ $provider->name }}" class="line-clamp-2 break-words font-bold leading-snug group-hover:text-brand-700">
                    <bdi>{{ $provider->name }}</bdi>
                    @if ($provider->is_verified)
                        <x-ui-icon name="check" class="inline-block h-4 w-4 align-[-2px] text-brand-500" title="{{ __('موثّق') }}" />
                    @endif
                </h3>
                @if ($handle = $provider->telegramHandle())
                    <p class="mt-0.5 flex items-center gap-1 text-xs text-sky-700" title="{{ $provider->telegram_url }}">
                        <x-ui-icon name="telegram" class="h-3.5 w-3.5 shrink-0" />
                        <span dir="ltr" class="truncate">{{ $handle }}</span>
                    </p>
                @endif
            </div>
        </div>

        {{-- Rating: score box, stars and count --}}
        <div class="flex items-center gap-2.5 rounded-xl bg-gray-50 px-3 py-2">
            <span class="grid h-9 min-w-9 place-items-center rounded-lg px-1.5 text-sm font-bold tabular-nums {{ $scoreClass }}">
                {{ $rated ? number_format($provider->rating_avg, 1) : '—' }}
            </span>
            <div class="min-w-0">
                <x-stars :rating="$provider->rating_avg" size="sm" />
                <p class="mt-0.5 truncate text-xs text-gray-500">
                    @if ($rated)
                        <span class="font-semibold text-gray-700">{{ \App\Models\Provider::ratingLabel((float) $provider->rating_avg) }}</span>
                        · {{ trans_choice(':count تقييم', $provider->reviews_count) }}
                    @else
                        {{ __('لا توجد تقييمات بعد') }}
                    @endif
                </p>
            </div>
        </div>

        @if ($provider->tr('service_type'))
            <p class="line-clamp-2 text-sm leading-relaxed text-gray-600">{{ $provider->tr('service_type') }}</p>
        @endif
    </div>

    {{-- What it covers --}}
    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 px-4 py-3 text-xs">
        @foreach ($categories->take(2) as $category)
            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-brand-800">{{ $category->icon }} {{ $category->tr('name') }}</span>
        @endforeach
        @if ($categories->count() > 2)
            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-brand-800" title="{{ $categories->skip(2)->map->tr('name')->join(__('trust.analysis.list_separator')) }}">+{{ $categories->count() - 2 }}</span>
        @endif
        @if ($provider->markets->isNotEmpty())
            <span class="truncate text-gray-500" title="{{ $provider->markets->map->tr('name')->join(__('trust.analysis.list_separator')) }}">
                {{ $provider->markets->take(3)->map->tr('name')->join(' · ') }}@if ($provider->markets->count() > 3) +{{ $provider->markets->count() - 3 }}@endif
            </span>
        @endif
        @if ($provider->pricingLabel())
            <span class="ms-auto shrink-0 rounded-full border border-gray-200 px-2 py-0.5 text-gray-600">{{ $provider->pricingLabel() }}</span>
        @endif
    </div>
</a>
