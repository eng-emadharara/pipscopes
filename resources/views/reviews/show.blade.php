<x-layouts.app
    :title="__('تقييم :name: :title', ['name' => $provider->name, 'title' => $review->title])"
    :description="\Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($review->body), 180)"
    :og-image="route('share-images.review', [app()->getLocale(), $review])"
    og-type="article">
    <div class="mx-auto max-w-3xl px-4 py-10">
        {{-- The provider this review is about --}}
        <a href="{{ route('providers.show', $provider) }}" class="card mb-6 flex items-center gap-4 p-4 transition hover:border-gray-400">
            <x-avatar :provider="$provider" size="h-14 w-14 text-xl" />
            <div class="min-w-0 flex-1">
                <div class="truncate font-bold"><bdi>{{ $provider->name }}</bdi></div>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <x-stars :rating="$provider->rating_avg" size="sm" />
                    <span class="font-semibold text-ink">{{ $provider->reviews_count ? number_format($provider->rating_avg, 1) : '—' }}</span>
                    <span>{{ trans_choice(':count تقييم', $provider->reviews_count) }}</span>
                </div>
            </div>
            <span class="hidden text-sm font-semibold text-brand-600 sm:inline">{{ __('عرض الصفحة') }} <span class="inline-block rtl:rotate-180">→</span></span>
        </a>

        <x-review-card :review="$review" :provider="$provider" :voted="$voted" :reported="$reported" />

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('providers.show', $provider) }}#reviews" class="btn-outline">{{ __('كل تقييمات :name', ['name' => $provider->name]) }}</a>
            <a href="{{ route('reviews.create', $provider) }}" class="btn-primary">{{ __('اكتب تقييمك') }}</a>
        </div>
    </div>
</x-layouts.app>
