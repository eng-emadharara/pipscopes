@php
    $principles = [
        ['icon' => 'check', 'title' => __('تقييم واحد لكل حساب مؤكد'), 'text' => __('كل مقيّم يؤكد بريده الإلكتروني، ولا يستطيع تقييم القناة نفسها أكثر من مرة.')],
        ['icon' => 'shield', 'title' => __('لا نحذف التقييمات مقابل المال'), 'text' => __('لا تستطيع أي قناة أن تدفع لإخفاء تقييم سلبي أو لرفع تقييمها.')],
        ['icon' => 'users', 'title' => __('اسمك مخفي إن أردت'), 'text' => __('انشر تجربتك دون إظهار اسمك للزوار ولا لصاحب القناة.')],
        ['icon' => 'share', 'title' => __('صاحب القناة يرد ولا يحذف'), 'text' => __('أصحاب القنوات يردّون علناً على التقييمات، ولا يستطيعون حذفها أو تقييم أي قناة.')],
    ];
@endphp
<section class="mx-auto mt-24 max-w-6xl px-4">
    <div class="grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="lg:sticky lg:top-8">
                <x-section-heading class="!mb-6" :eyebrow="__('مبادئنا')" :title="__('كيف نضمن المصداقية')"
                                   :intro="__('قواعد واضحة تُطبَّق على الجميع، من المقيّمين إلى أصحاب القنوات.')" />
                <a href="{{ route('pages.show', 'about') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    {{ __('من نحن') }}
                    <span class="inline-block transition group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5">→</span>
                </a>
            </div>
        </div>

        <ol class="grid gap-px overflow-hidden rounded-3xl bg-gray-200/70 ring-1 ring-gray-200/70 sm:grid-cols-2 lg:col-span-8" data-reveal="stagger">
            @foreach ($principles as $item)
                <li class="group bg-white p-6 transition hover:bg-[#fefefc] md:p-7">
                    <div class="flex items-center justify-between">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white">
                            <x-ui-icon :name="$item['icon']" class="h-5 w-5" />
                        </span>
                        <span class="font-mono text-sm tabular-nums text-gray-300">{{ sprintf('%02d', $loop->iteration) }}</span>
                    </div>
                    <h3 class="mt-5 font-bold">{{ $item['title'] }}</h3>
                    <p class="mt-1.5 text-pretty text-sm leading-relaxed text-gray-600">{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
