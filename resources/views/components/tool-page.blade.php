{{-- The frame of each trader calculator (resources/views/tools/*): heading, the tool, how it works, the other tools, and the channel check --}}
@props(['title', 'intro', 'description', 'current'])
@php
    $tools = [
        'tools.money-management' => [__('حاسبة إدارة رأس المال'), __('خطة كاملة حسب رأس مالك وأسلوبك: المخاطرة، اللوت، ومتى تتوقف.')],
        'tools.lot-size' => [__('حاسبة حجم اللوت'), __('كم لوت تفتح حسب رأس مالك ونسبة المخاطرة؟')],
        'tools.pip-value' => [__('حاسبة قيمة النقطة'), __('كم دولاراً تساوي النقطة في صفقتك؟')],
        'tools.profit' => [__('حاسبة الربح والخسارة'), __('كم تربح أو تخسر بين سعر الدخول والخروج؟')],
    ];
@endphp
<x-layouts.app :title="$title" :description="$description">
    <section class="bg-ink text-white">
        <div class="mx-auto max-w-3xl px-4 py-8 text-center md:py-10">
            <a href="{{ route('tools.index') }}" class="text-xs font-semibold text-brand-500 hover:underline">{{ __('أدوات المتداول المجانية') }}</a>
            <h1 class="mt-2 text-2xl font-bold md:text-4xl">{{ $title }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm text-gray-300 md:text-base">{{ $intro }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        {{ $slot }}

        @isset($explain)
            <section class="card p-6 text-sm leading-relaxed text-gray-700">{{ $explain }}</section>
        @endisset

        {{-- The channel check: the reason these tools are here --}}
        <a href="{{ route('channel-check') }}" class="card flex items-center gap-4 border-brand-100 bg-brand-50 p-5 hover:border-brand-500" data-tool-check-link>
            <x-ui-icon name="shield" class="h-8 w-8 shrink-0 text-brand-600" />
            <span>
                <span class="block font-bold text-ink">{{ __('قبل أن تتبع توصيات أي قناة… افحصها مجاناً') }}</span>
                <span class="block text-sm text-gray-600">{{ __('هل جمهورها حقيقي؟ هل تنشر صفقاتها قبل حدوثها؟ هل تذكر خساراتها؟') }}</span>
            </span>
        </a>

        <nav class="grid gap-3 sm:grid-cols-2" aria-label="{{ __('أدوات أخرى') }}">
            @foreach ($tools as $route => [$name, $text])
                @continue($route === $current)
                <a href="{{ route($route) }}" class="card p-4 hover:border-brand-500">
                    <span class="block font-bold">{{ $name }}</span>
                    <span class="block text-sm text-gray-600">{{ $text }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</x-layouts.app>
