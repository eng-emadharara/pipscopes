{{-- "Join our Telegram channel" — link from Site settings, texts from Home sections settings --}}
@php
    $socials = \App\Support\Site::socials();
    // The other platforms, shown as small "also follow us" icons
    $others = collect($socials)->except('telegram');
@endphp
<section class="mx-auto mt-24 max-w-6xl px-4" data-reveal>
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-l from-sky-50 to-white shadow-float ring-1 ring-sky-200/70">
        <div class="pointer-events-none absolute -top-24 end-[-4rem] h-64 w-64 rounded-full bg-sky-200/40 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col items-center gap-6 p-6 text-center md:flex-row md:p-10 md:text-start">
            <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-sky-500 text-white shadow-lg shadow-sky-500/30">
                <x-ui-icon name="telegram" class="h-9 w-9" />
            </span>
            <div class="flex-1">
                <h2 class="text-xl font-bold md:text-2xl">{{ \App\Support\HomeSections::telegramText('title', __('انضم لقناتنا على تيليجرام')) }}</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ \App\Support\HomeSections::telegramText('text', __('تنبيهات بالحسابات المنتحلة، وأحدث القنوات والتقييمات على المنصة، أولاً بأول.')) }}</p>
                @if ($others->isNotEmpty())
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-gray-500 md:justify-start">
                        <span>{{ __('تابعنا أيضاً على') }}</span>
                        @foreach ($others as $platform => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ \App\Filament\Pages\SiteSettings::SOCIALS[$platform][0] ?? $platform }}"
                               class="grid h-8 w-8 place-items-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-sky-400 hover:text-sky-600">
                                <x-ui-icon :name="$platform" class="h-4 w-4" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <a href="{{ $socials['telegram'] }}" target="_blank" rel="noopener"
               class="inline-flex shrink-0 items-center gap-2 rounded-full bg-sky-500 px-6 py-3 font-semibold text-white shadow-lg shadow-sky-500/25 transition hover:bg-sky-600 active:scale-[.98]">
                <x-ui-icon name="telegram" class="h-5 w-5" />
                {{ __('انضم للقناة') }}
            </a>
        </div>
    </div>
</section>
