{{-- Two channels side by side (ChannelCompareController). The URL carries both, so a comparison can be shared. --}}
@php
    $tones = ['good' => ['bg-brand-500', 'text-brand-700'], 'warn' => ['bg-amber-400', 'text-amber-700'], 'bad' => ['bg-red-500', 'text-red-700'], 'pending' => ['bg-gray-300', 'text-gray-500']];
    $names = $reports ? array_map(fn ($r) => $r['channel']['title'] ?? null, $reports) : [];
@endphp
<x-layouts.app :title="$reports && $names[0] && $names[1] ? __(':a أم :b؟ مقارنة القناتين', ['a' => $names[0], 'b' => $names[1]]) : __('قارن بين قناتي توصيات')"
               :description="__('ضع قناتين على تيليجرام جنباً إلى جنب: التفاعل الحقيقي، الصفقات مقابل الادعاءات، الخسائر، والعمر. مجاناً.')">
    <section class="bg-ink text-white">
        <div class="mx-auto max-w-3xl px-4 py-10 text-center md:py-14">
            <h1 class="text-2xl font-bold md:text-4xl">{{ __('قارن بين قناتي توصيات') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm text-gray-300 md:text-base">{{ __('محتار بين قناتين؟ ضعهما جنباً إلى جنب وشاهد ما تقوله أرقام كل منهما.') }}</p>

            <form method="GET" action="{{ route('channel-compare') }}" class="mx-auto mt-6 grid max-w-2xl gap-2 sm:grid-cols-[1fr_auto_1fr_auto] sm:items-center" data-check-form>
                <input type="text" name="a" value="{{ $a }}" dir="ltr" required maxlength="255" autocomplete="off" placeholder="@channel1"
                       class="input text-start text-ink" aria-label="{{ __('القناة الأولى') }}">
                <span class="text-sm font-bold text-gray-400" aria-hidden="true">{{ __('مقابل') }}</span>
                <input type="text" name="b" value="{{ $b }}" dir="ltr" required maxlength="255" autocomplete="off" placeholder="@channel2"
                       class="input text-start text-ink" aria-label="{{ __('القناة الثانية') }}">
                <button class="btn-primary">{{ __('قارن') }}</button>
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-4xl px-4 py-8" @if ($reports) data-compare-result @endif>
        @if ($reports)
            {{-- The two channels --}}
            <div class="grid grid-cols-2 gap-3">
                @foreach ($reports as $report)
                    <div class="card p-4 text-center" data-compare-channel="{{ $loop->index }}">
                        @if ($report['status'] === 'report')
                            @if ($report['channel']['photo_url'])
                                <img src="{{ $report['channel']['photo_url'] }}" alt="" class="mx-auto h-14 w-14 rounded-2xl border border-gray-200 object-cover" referrerpolicy="no-referrer">
                            @endif
                            <h2 class="mt-2 break-words font-bold"><bdi>{{ $report['channel']['title'] }}</bdi></h2>
                            <p class="text-xs text-sky-700" dir="ltr">@<span>{{ $report['channel']['username'] }}</span></p>
                            @if ($report['channel']['subscribers'])
                                <p class="mt-1 text-xs text-gray-500">{{ trans_choice(':count مشترك', $report['channel']['subscribers'], ['count' => number_format($report['channel']['subscribers'])]) }}</p>
                            @endif
                            @if ($report['trading'] === \App\Support\TradingChannel::NOT_TRADING)
                                <p class="mt-2 rounded-lg bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ __('لا تبدو قناة تداول') }}</p>
                            @endif
                            <a href="{{ route('channel-check', ['channel' => '@'.$report['channel']['username']]) }}" class="mt-3 inline-block text-xs font-semibold text-brand-700 hover:underline">{{ __('التقرير الكامل') }} ←</a>
                        @elseif ($report['status'] === 'impersonator')
                            <p class="text-sm font-bold text-red-700">⚠️ {{ __('تحذير: هذا حساب منتحل معروف') }}</p>
                        @else
                            <p class="text-sm text-amber-800">{{ $report['message'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Indicator by indicator: what it measures, then each channel's value --}}
            @if ($keys)
                <div class="card mt-5 divide-y divide-gray-100 p-0" data-compare-rows>
                    @foreach ($keys as $key)
                        @php($label = collect($reports)->map(fn ($r) => $r['cards'][$key]['label'] ?? null)->filter()->first())
                        <div class="p-4" data-compare-row="{{ $key }}">
                            <p class="mb-2 text-center text-xs font-semibold text-gray-500">{{ $label }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($reports as $report)
                                    @php($card = $report['cards'][$key] ?? null)
                                    <div class="text-center">
                                        @if ($card)
                                            <p class="flex items-center justify-center gap-1.5 font-bold tabular-nums">
                                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $tones[$card['level']][0] }}" aria-hidden="true"></span>
                                                <span dir="auto">{{ $card['value'] }}</span>
                                            </p>
                                            <p class="mt-0.5 text-xs {{ $tones[$card['level']][1] }}">{{ $card['status'] }}</p>
                                        @else
                                            <p class="text-sm text-gray-400">—</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-center text-xs text-gray-500">{{ __('«—» يعني أنه لا توجد بيانات كافية لهذا المؤشر.') }}</p>
            @endif

            <p class="mt-6 text-center text-xs leading-relaxed text-gray-400">{{ __('التقرير محسوب تلقائياً من بيانات القناة العامة على تيليجرام، وهو ليس نصيحة مالية.') }}</p>
        @else
            <p class="text-center text-sm text-gray-600">{{ __('الصق رابطي القناتين أو معرّفيهما، مثل @channel أو t.me/channel.') }}</p>
        @endif
    </div>
</x-layouts.app>
