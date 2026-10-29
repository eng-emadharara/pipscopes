{{-- Category cards; right under the hero they overlap its bottom edge --}}
@if ($categories->isNotEmpty())
    <section @class(['mx-auto max-w-6xl px-4', '-mt-8' => $first, 'mt-14' => ! $first])>
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
