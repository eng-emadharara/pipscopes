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

    <div class="grid gap-6 sm:grid-cols-3">
        <x-trend-line :title="__('المشتركون')" :points="$series('subscribers')" />
        <x-trend-line :title="__('متوسط المشاهدات')" :points="$series('avg_views')" />
        <x-trend-line :title="__('نسبة المشاهدات للمشتركين')" :points="$series('ratio')" format="percent" />
    </div>

    <p class="mt-3 text-xs leading-relaxed text-gray-500">{{ __('نسبة المشاهدات للمشتركين تقيس تفاعل الجمهور: القنوات النشطة غالباً بين 10% و40%، والنسبة المنخفضة جداً قد تعني مشتركين غير نشطين أو غير حقيقيين.') }}</p>

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
                            <td class="py-1.5 text-start">{{ $day['date']->translatedFormat('l j/n') }}</td>
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
