@props(['rating' => 0, 'size' => 'md'])
@php
    $rating = (float) $rating;
    $color = \App\Models\Provider::ratingColor($rating);
    $box = ['sm' => 'h-4 w-4', 'md' => 'h-6 w-6', 'lg' => 'h-9 w-9'][$size] ?? 'h-6 w-6';
    $colorClass = [
        'excellent' => 'bg-rate-excellent', 'great' => 'bg-rate-great', 'average' => 'bg-rate-average',
        'poor' => 'bg-rate-poor', 'bad' => 'bg-rate-bad', 'none' => 'bg-rate-none',
    ][$color];
@endphp
<div {{ $attributes->merge(['class' => 'inline-flex gap-0.5']) }} role="img" aria-label="{{ __(":rating من 5", ["rating" => number_format($rating, 1)]) }}">
    @for ($i = 1; $i <= 5; $i++)
        @php $fill = max(0, min(1, $rating - ($i - 1))); @endphp
        <span class="relative {{ $box }} overflow-hidden rounded-[3px] bg-rate-none">
            {{-- Partial fill grows from the start edge (right in RTL, left in LTR) --}}
            <span class="absolute inset-y-0 start-0 {{ $colorClass }}" style="width: {{ round($fill * 100) }}%"></span>
            <x-ui-icon name="star" class="relative h-full w-full p-[12%] fill-white" />
        </span>
    @endfor
</div>
