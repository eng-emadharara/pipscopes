{{-- One indicator of the channel report, laid out in full: what it measures, the number, and what it means (nothing to click) --}}
@php
    $tones = [
        'good' => ['bg-brand-500', 'text-brand-700'],
        'warn' => ['bg-amber-400', 'text-amber-700'],
        'bad' => ['bg-red-500', 'text-red-700'],
        'pending' => ['bg-gray-300', 'text-gray-600'],
    ][$card['level']];
@endphp
<li class="flex gap-3 p-4" data-insight="{{ $card['key'] }}" data-level="{{ $card['level'] }}">
    <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $tones[0] }}" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <span class="text-sm text-gray-500">{{ $card['label'] }}</span>
            <span class="text-lg font-bold tabular-nums" dir="auto">{{ $card['value'] }}</span>
        </div>
        <p class="mt-0.5 text-sm font-semibold {{ $tones[1] }}">{{ $card['status'] }}</p>
        <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $card['note'] }}</p>
    </div>
</li>
