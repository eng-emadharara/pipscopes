@php
    $principles = [
        ['icon' => 'check', 'title' => __('تقييم واحد لكل حساب مؤكد'), 'text' => __('كل مقيّم يؤكد بريده الإلكتروني، ولا يستطيع تقييم القناة نفسها أكثر من مرة.')],
        ['icon' => 'shield', 'title' => __('لا نحذف التقييمات مقابل المال'), 'text' => __('لا تستطيع أي قناة أن تدفع لإخفاء تقييم سلبي أو لرفع تقييمها.')],
        ['icon' => 'users', 'title' => __('اسمك مخفي إن أردت'), 'text' => __('انشر تجربتك دون إظهار اسمك للزوار ولا لصاحب القناة.')],
        ['icon' => 'share', 'title' => __('صاحب القناة يرد ولا يحذف'), 'text' => __('أصحاب القنوات يردّون علناً على التقييمات، ولا يستطيعون حذفها أو تقييم أي قناة.')],
    ];
@endphp
<section class="mx-auto mt-14 max-w-6xl px-4">
    <div class="mb-5 flex items-end justify-between gap-4">
        <h2 class="text-2xl font-bold">{{ \App\Support\HomeSections::title($section) }}</h2>
        <a href="{{ route('pages.show', 'about') }}" class="shrink-0 text-sm font-semibold text-brand-600 hover:underline">{{ __('من نحن') }} <span class="inline-block rtl:rotate-180">→</span></a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($principles as $item)
            <div class="card p-5">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <x-ui-icon :name="$item['icon']" class="h-5 w-5" />
                </span>
                <h3 class="mt-3 font-bold">{{ $item['title'] }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $item['text'] }}</p>
            </div>
        @endforeach
    </div>
</section>
