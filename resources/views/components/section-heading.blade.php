{{-- Homepage section title: small eyebrow, heading, optional intro line and a "see more" link at the end --}}
@props(['eyebrow' => null, 'title', 'intro' => null, 'href' => null, 'link' => null, 'tone' => 'brand'])
@php
    $eyebrowClass = ['brand' => 'text-brand-600', 'danger' => 'text-red-600'][$tone] ?? 'text-brand-600';
@endphp
<div {{ $attributes->merge(['class' => 'mb-8 flex flex-wrap items-end justify-between gap-x-6 gap-y-3']) }}>
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p class="mb-2 flex items-center gap-2 text-sm font-semibold {{ $eyebrowClass }}">
                <span class="h-px w-6 bg-current"></span>
                {{ $eyebrow }}
            </p>
        @endif
        <h2 class="text-2xl font-bold tracking-tight text-balance md:text-3xl">{{ $title }}</h2>
        @if ($intro)
            <p class="mt-2 text-pretty leading-relaxed text-gray-600">{{ $intro }}</p>
        @endif
    </div>
    @if ($href)
        <a href="{{ $href }}" class="group inline-flex shrink-0 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold transition hover:border-ink active:scale-[.98]">
            {{ $link }}
            <span class="inline-block transition group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5">→</span>
        </a>
    @endif
</div>
