{{-- Team picks (Admin → اختيارات الفريق): the admin's order, each with why it was chosen --}}
@if ($picks->isNotEmpty())
    <section @class(['mx-auto max-w-6xl px-4', 'mt-10' => $first, 'mt-14' => ! $first])>
        <div class="mb-5">
            <h2 class="flex items-center gap-2 text-2xl font-bold">
                <x-ui-icon name="sparkles" class="h-6 w-6 text-brand-600" />
                {{ \App\Support\HomeSections::title($section) }}
            </h2>
            {{-- Says plainly that these aren't paid placements --}}
            <p class="mt-1 text-sm text-gray-600">
                {{ __('قنوات يختارها فريقنا بناءً على التقييمات والالتزام والشفافية. لا نقبل أي مقابل لإدراج قناة هنا.') }}
                <a href="{{ route('pages.show', 'about') }}" class="font-semibold text-brand-700 hover:underline">{{ __('كيف نعمل') }}</a>
            </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($picks as $pick)
                <x-provider-card :provider="$pick->provider" :note="$pick->tr('note')" />
            @endforeach
        </div>
    </section>
@endif
