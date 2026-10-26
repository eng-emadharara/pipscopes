@php
    $locales = config('trust.locales');
    $current = app()->getLocale();
@endphp
@if (count($locales) === 2)
    {{-- Two languages: a single toggle to the other one --}}
    @foreach ($locales as $code => $meta)
        @continue($code === $current)
        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" class="rounded-full border border-white/20 px-3 py-1 hover:bg-white/10">{{ $meta['name'] }}</a>
    @endforeach
@else
    <details class="relative">
        <summary class="cursor-pointer list-none rounded-full border border-white/20 px-3 py-1 hover:bg-white/10">{{ $locales[$current]['name'] }}</summary>
        <div class="absolute end-0 z-20 mt-2 min-w-32 rounded-xl bg-white py-1 text-ink shadow-lg">
            @foreach ($locales as $code => $meta)
                <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" class="block px-4 py-2 text-sm hover:bg-gray-100 {{ $code === $current ? 'font-semibold' : '' }}">{{ $meta['name'] }}</a>
            @endforeach
        </div>
    </details>
@endif
