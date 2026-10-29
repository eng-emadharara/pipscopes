<section @class(['mx-auto max-w-6xl px-4', 'mt-10' => $first, 'mt-14' => ! $first])>
    <div class="mb-5 flex items-end justify-between">
        <h2 class="text-2xl font-bold">{{ \App\Support\HomeSections::title($section) }}</h2>
        <a href="{{ route('providers.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('عرض الكل') }} <span class="inline-block rtl:rotate-180">→</span></a>
    </div>
    @if ($featured->isEmpty())
        <div class="card p-10 text-center text-gray-500">{{ __('لا يوجد مقدمو خدمة بعد.') }}</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($featured as $provider)
                <x-provider-card :provider="$provider" />
            @endforeach
        </div>
    @endif
</section>
