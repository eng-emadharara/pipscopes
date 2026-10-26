<x-layouts.app :title="$page->tr('title')" :description="$page->tr('subtitle')">
    {{-- Hero --}}
    <section class="bg-ink pb-20 pt-14 text-white">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <h1 class="text-3xl font-bold leading-tight md:text-5xl">{{ $page->tr('title') }}</h1>
            @if ($page->tr('subtitle'))
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-gray-300">{{ $page->tr('subtitle') }}</p>
            @endif
            @unless ($page->is_published)
                <p class="mt-4 inline-block rounded-full bg-amber-400 px-3 py-1 text-xs font-semibold text-ink">{{ __('مسودة: الصفحة غير منشورة ويراها المدراء فقط') }}</p>
            @endunless
        </div>
    </section>

    <div class="mx-auto -mt-10 max-w-3xl space-y-5 px-4">
        @if ($content['intro'])
            <div class="card rich-content p-6 text-lg shadow-sm md:p-8">{!! $content['intro'] !!}</div>
        @endif

        @foreach ($content['sections'] as $i => $section)
            <section class="card p-6 md:p-8 {{ $loop->first && ! $content['intro'] ? 'shadow-sm' : '' }}">
                <div class="mb-4 flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-700">{{ $i + 1 }}</span>
                    <h2 class="text-xl font-bold md:text-2xl">{{ $section['heading'] }}</h2>
                </div>
                <div class="rich-content">{!! $section['html'] !!}</div>
            </section>
        @endforeach

        {{-- Live numbers --}}
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="card p-4">
                <div class="text-2xl font-bold text-brand-600">{{ number_format($stats['providers']) }}</div>
                <div class="text-xs text-gray-500">{{ __('مقدم خدمة') }}</div>
            </div>
            <div class="card p-4">
                <div class="text-2xl font-bold text-brand-600">{{ number_format($stats['reviews']) }}</div>
                <div class="text-xs text-gray-500">{{ __('تقييم منشور') }}</div>
            </div>
            <div class="card p-4">
                <div class="text-2xl font-bold text-brand-600">{{ number_format($stats['categories']) }}</div>
                <div class="text-xs text-gray-500">{{ __('تصنيف') }}</div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="card flex flex-col items-center gap-4 bg-brand-50 p-8 text-center">
            <h2 class="text-xl font-bold">{{ __('تجربتك قد تحمي متداولاً آخر من الخسارة') }}</h2>
            <p class="max-w-lg text-sm text-gray-600">{{ __('ابحث عن القناة أو مقدم الخدمة الذي تعاملت معه وشارك تقييمك بصدق، باسمك أو بدون اسم.') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('providers.index') }}" class="btn-primary">{{ __('تصفح وقيّم مقدمي الخدمة') }}</a>
                <a href="{{ route('submissions.create') }}" class="btn-outline">{{ __('أضف مقدم خدمة') }}</a>
            </div>
        </div>
    </div>
</x-layouts.app>
