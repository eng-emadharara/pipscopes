{{-- One measure over the last days as a single line (small multiples: one chart per measure, never two
     scales on one axis). $points: list of ['date' => Carbon, 'value' => ?float]; $format: number | percent. --}}
@props(['title', 'points', 'format' => 'number'])
@php
    $fmt = function (?float $v, bool $short = false) use ($format) {
        if ($v === null) return '—';
        if ($format === 'percent') return rtrim(rtrim(number_format($v, $v < 10 ? 1 : 0), '0'), '.').'%';
        if ($short && abs($v) >= 10000) return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'K';
        return number_format($v);
    };

    $known = array_values(array_filter($points, fn ($p) => $p['value'] !== null));
    $values = array_column($known, 'value') ?: [0];
    $lo = min($values); $hi = max($values);
    // Grid lines at the data's own top, middle and bottom (one line when it's flat)
    $ticks = $hi - $lo < 1e-9 ? [$hi] : [$hi, ($hi + $lo) / 2, $lo];
    // A flat line sits in the middle rather than on an edge
    if ($hi - $lo < 1e-9) { $pad = max(abs($hi) * 0.05, $format === 'percent' ? 0.5 : 1); $lo -= $pad; $hi += $pad; }
    else { $pad = ($hi - $lo) * 0.15; $lo -= $pad; $hi += $pad; }
    if ($lo < 0) $lo = 0;

    $x0 = 44; $x1 = 308; $y0 = 14; $y1 = 102;
    $n = count($points);
    $X = fn (int $i) => $n > 1 ? $x0 + ($x1 - $x0) * $i / ($n - 1) : ($x0 + $x1) / 2;
    $Y = fn (float $v) => $y1 - ($v - $lo) / ($hi - $lo) * ($y1 - $y0);

    // Path segments break where a day is unknown
    $path = ''; $open = false;
    foreach ($points as $i => $p) {
        if ($p['value'] === null) { $open = false; continue; }
        $path .= ($open ? 'L' : 'M').round($X($i), 1).' '.round($Y($p['value']), 1);
        $open = true;
    }

    $latest = $known ? end($known)['value'] : null;
    $change = count($known) > 1 ? $latest - $known[0]['value'] : null;
@endphp
<figure {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <figcaption class="text-xs text-gray-500">{{ $title }}</figcaption>
    <div class="mt-0.5 flex items-baseline gap-2">
        <span class="text-xl font-bold tabular-nums" dir="ltr">{{ $fmt($latest) }}</span>
        @if ($change !== null && abs($change) > 1e-9)
            <span class="text-xs tabular-nums text-gray-500" dir="ltr">{{ $change > 0 ? '▲' : '▼' }} {{ $change > 0 ? '+' : '−' }}{{ $fmt(abs($change)) }}</span>
        @endif
    </div>

    {{-- Time runs left to right, as on any trading chart --}}
    <svg viewBox="0 0 320 128" class="mt-1 w-full text-brand-600" dir="ltr" role="img" aria-label="{{ $title }}: {{ $fmt($latest) }}">
        @foreach ($ticks as $t)
            <line x1="{{ $x0 }}" x2="{{ $x1 }}" y1="{{ round($Y($t), 1) }}" y2="{{ round($Y($t), 1) }}" stroke="#e5e7eb" stroke-width="1" />
            <text x="{{ $x0 - 6 }}" y="{{ round($Y($t), 1) + 3.5 }}" text-anchor="end" font-size="10" fill="#9ca3af">{{ $fmt($t, true) }}</text>
        @endforeach

        <path d="{{ $path }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

        @foreach ($points as $i => $p)
            <text x="{{ round($X($i), 1) }}" y="122" text-anchor="middle" font-size="10" fill="#9ca3af">{{ $p['date']->format('j/n') }}</text>
            {{-- A tall hit area per day carries the tooltip; the dot grows on hover --}}
            <g class="group">
                <rect x="{{ round($X($i) - ($x1 - $x0) / max(1, $n - 1) / 2, 1) }}" y="{{ $y0 - 6 }}" width="{{ round(($x1 - $x0) / max(1, $n - 1), 1) }}" height="{{ $y1 - $y0 + 12 }}" fill="transparent">
                    <title>{{ $p['date']->translatedFormat('l j/n') }}: {{ $fmt($p['value']) }}</title>
                </rect>
                @if ($p['value'] !== null)
                    <line x1="{{ round($X($i), 1) }}" x2="{{ round($X($i), 1) }}" y1="{{ $y0 }}" y2="{{ $y1 }}" stroke="#d1d5db" stroke-width="1" class="pointer-events-none opacity-0 group-hover:opacity-100" />
                    <circle cx="{{ round($X($i), 1) }}" cy="{{ round($Y($p['value']), 1) }}" r="{{ $loop->last ? 4 : 3 }}" fill="currentColor" stroke="#fff" stroke-width="2" class="pointer-events-none transition-[r] group-hover:[r:5]" />
                @endif
            </g>
        @endforeach
    </svg>
</figure>
