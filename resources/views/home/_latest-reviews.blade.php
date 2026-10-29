@if ($latestReviews->isNotEmpty())
    <section @class(['mx-auto max-w-6xl px-4', 'mt-10' => $first, 'mt-14' => ! $first])>
        <h2 class="mb-5 text-2xl font-bold">{{ \App\Support\HomeSections::title($section) }}</h2>
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
