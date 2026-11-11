{{-- One indicator (App\Support\TelegramInsights / ChannelReport): a number with a coloured dot; opens to explain itself --}}
@php($dots = ['good' => 'bg-brand-500', 'warn' => 'bg-amber-400', 'bad' => 'bg-red-500', 'pending' => 'bg-gray-300'])
<details class="group rounded-xl border border-gray-200 bg-white p-3 open:col-span-full open:bg-gray-50" data-insight="{{ $card['key'] }}" data-level="{{ $card['level'] }}"
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
