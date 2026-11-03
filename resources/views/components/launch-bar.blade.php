{{-- The beta period: everything is open, with a countdown to the official launch on every page. Closed by a visitor, it stays away until their next visit. --}}
@if (\App\Support\Launch::isBeta())
    @php($launchAt = \App\Support\Launch::launchAt())
    <div class="bg-brand-700 text-white" data-launch-bar>
        <div class="relative mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-1.5 px-9 py-2 text-center text-xs md:text-sm">
            <span aria-hidden="true">🚀</span>
            <strong>{{ __('النسخة التجريبية') }}</strong>
            <span aria-hidden="true">·</span>
            @if ($launchAt)
                <span>{{ __('الإطلاق الرسمي خلال') }}</span>
                {{-- Filled in by resources/js/countdown.js, which reloads the page when it ends so the bar goes away --}}
                <span class="whitespace-nowrap font-bold tabular-nums" data-countdown="{{ $launchAt->toIso8601String() }}" data-reload-on-end="1">
                    <span data-unit="days">--</span> {{ __('يوم') }}
                    <span dir="ltr"><span data-unit="hours">--</span>:<span data-unit="minutes">--</span>:<span data-unit="seconds">--</span></span>
                </span>
            @else
                <span>{{ __('الإطلاق الرسمي قريباً') }}</span>
            @endif
            <button type="button" class="absolute end-2 top-1/2 -translate-y-1/2 px-1.5 text-lg leading-none text-white/80 hover:text-white" data-launch-bar-close aria-label="{{ __('إغلاق') }}">&times;</button>
        </div>
    </div>
    {{-- Before the first paint, so a closed bar doesn't flash on every page --}}
    <script>try { if (sessionStorage.getItem('tt:launch-bar-closed')) document.querySelector('[data-launch-bar]').remove(); } catch {}</script>
@endif
