{{-- Site pagination (default for ->links(), set in AppServiceProvider). Arrows flip with the page direction. --}}
@if ($paginator->hasPages())
    @php
        $item = 'inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-sm font-medium transition';
        $link = $item.' border-gray-200 bg-white text-ink hover:border-gray-400';
        $disabled = $item.' cursor-default border-gray-100 bg-gray-50 text-gray-300';
    @endphp
    <nav role="navigation" aria-label="{{ __('التنقل بين الصفحات') }}" class="flex flex-col items-center gap-3">
        <div class="flex flex-wrap items-center justify-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true">
                    <span aria-hidden="true" class="rtl:rotate-180">‹</span>
                    <span class="ms-1 hidden sm:inline">{{ __('السابق') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}" aria-label="{{ __('الصفحة السابقة') }}">
                    <span aria-hidden="true" class="rtl:rotate-180">‹</span>
                    <span class="ms-1 hidden sm:inline">{{ __('السابق') }}</span>
                </a>
            @endif

            {{-- Page numbers; on phones only the current page is shown between the arrows --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="hidden px-1 text-gray-400 sm:inline" aria-hidden="true">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $item }} border-brand-500 bg-brand-500 font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $link }} hidden sm:inline-flex" aria-label="{{ __('الذهاب إلى الصفحة :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}" aria-label="{{ __('الصفحة التالية') }}">
                    <span class="me-1 hidden sm:inline">{{ __('التالي') }}</span>
                    <span aria-hidden="true" class="rtl:rotate-180">›</span>
                </a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true">
                    <span class="me-1 hidden sm:inline">{{ __('التالي') }}</span>
                    <span aria-hidden="true" class="rtl:rotate-180">›</span>
                </span>
            @endif
        </div>

        <p class="text-xs text-gray-500">{{ __('صفحة :current من :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</p>
    </nav>
@endif
