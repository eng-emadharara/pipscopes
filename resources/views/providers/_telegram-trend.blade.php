{{-- The channel's Telegram numbers over the last 7 days (App\Support\TelegramTrend); on/off in Site settings --}}
@php
    $series = fn (string $key) => array_map(fn ($p) => ['date' => $p['date'], 'value' => $p[$key] !== null ? (float) $p[$key] : null], $trend);
@endphp
<section class="card p-6" id="telegram-trend">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="flex items-center gap-2 text-lg font-bold">
            <x-ui-icon name="telegram" class="h-5 w-5 text-sky-500" />
            {{ __('أداء القناة على تيليجرام') }}
        </h2>
        <span class="text-xs text-gray-500">{{ __('آخر 7 أيام') }}</span>
    </div>

    {{-- What the channel's posts show (App\Support\TelegramInsights); each opens to explain itself --}}
    @php($insights = \App\Support\TelegramInsights::for($provider))
    @if ($insights)
        @php($dots = ['good' => 'bg-brand-500', 'warn' => 'bg-amber-400', 'bad' => 'bg-red-500', 'pending' => 'bg-gray-300'])
        <div @class(['mb-6 grid gap-2', 'grid-cols-3' => count($insights) === 3, 'grid-cols-2' => count($insights) === 2]) data-telegram-insights>
            @foreach ($insights as $card)
                <details class="group rounded-xl border border-gray-200 p-3 open:col-span-full open:bg-gray-50" data-insight="{{ $card['key'] }}" data-level="{{ $card['level'] }}"
                         data-track="telegram_insight_open" data-track-params='@json(['insight' => $card['key']])'>
                    <summary class="flex cursor-pointer list-none flex-col items-center gap-1 text-center [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center gap-1.5 text-lg font-bold tabular-nums md:text-xl">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dots[$card['level']] }}" aria-hidden="true"></span>
                            <span dir="auto">{{ $card['value'] }}</span>
                        </span>
                        <span class="text-xs leading-snug text-gray-500">{{ $card['label'] }}</span>
                    </summary>
                    <div class="mt-3 border-t border-gray-200 pt-3 text-start text-xs leading-relaxed text-gray-600">
                        <p class="font-semibold text-ink">{{ $card['status'] }}</p>
                        <p class="mt-1">{{ $card['note'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 sm:grid-cols-3">
        <x-trend-line :title="__('المشتركون')" :points="$series('subscribers')" />
        <x-trend-line :title="__('متوسط المشاهدات')" :points="$series('avg_views')" />
        <x-trend-line :title="__('نسبة المشاهدات للمشتركين')" :points="$series('ratio')" format="percent" />
    </div>

    <p class="mt-3 text-xs leading-relaxed text-gray-500">{{ __('نسبة المشاهدات للمشتركين تقيس تفاعل الجمهور: القنوات النشطة غالباً بين 10% و40%، والنسبة المنخفضة جداً قد تعني مشتركين غير نشطين أو غير حقيقيين.') }}</p>
    @if ($insights)
        <p class="mt-1 text-xs text-gray-400">{{ __('محسوبة تلقائياً من صفحة القناة العامة على تيليجرام، وتتحدّث يومياً.') }}</p>
    @endif

    {{-- The same numbers as a table, for screen readers and for reading exact values --}}
    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs font-semibold text-gray-600 hover:text-ink">{{ __('عرض الأرقام كجدول') }}</summary>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-center text-xs tabular-nums">
                <thead class="text-gray-500">
                    <tr class="border-b border-gray-100">
                        <th class="py-1.5 text-start font-medium">{{ __('اليوم') }}</th>
                        <th class="py-1.5 font-medium">{{ __('المشتركون') }}</th>
                        <th class="py-1.5 font-medium">{{ __('متوسط المشاهدات') }}</th>
                        <th class="py-1.5 font-medium">{{ __('النسبة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_reverse($trend) as $day)
                        <tr class="border-b border-gray-50">
                            <td class="py-1.5 text-start">
                                {{ $day['date']->translatedFormat('l j/n') }}
                                @if ($day['unchanged'])
                                    <span class="text-gray-400" title="{{ __('لم تتغير الأرقام عن اليوم السابق') }}">({{ __('بدون تغيير') }})</span>
                                @endif
                            </td>
                            <td class="py-1.5">{{ $day['subscribers'] !== null ? number_format($day['subscribers']) : '—' }}</td>
                            <td class="py-1.5">{{ $day['avg_views'] !== null ? number_format($day['avg_views']) : '—' }}</td>
                            <td class="py-1.5">{{ $day['ratio'] !== null ? $day['ratio'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</section>
