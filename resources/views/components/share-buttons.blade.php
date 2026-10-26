@props(['url', 'text', 'variant' => 'button'])
@php
    $u = rawurlencode($url);
    $t = rawurlencode($text);
    $networks = [
        ['telegram', __('تيليجرام'), "https://t.me/share/url?url=$u&text=$t", 'text-sky-600'],
        ['x', 'X', "https://twitter.com/intent/tweet?text=$t&url=$u", 'text-ink'],
        ['whatsapp', __('واتساب'), 'https://wa.me/?text='.rawurlencode($text.' '.$url), 'text-green-600'],
        ['facebook', __('فيسبوك'), "https://www.facebook.com/sharer/sharer.php?u=$u", 'text-blue-600'],
    ];
@endphp
{{-- Share menu; resources/js/share.js adds the phone's native share sheet and "copy link" --}}
<details {{ $attributes->merge(['class' => 'relative']) }} data-share>
    @if ($variant === 'button')
        {{-- Icon only on phones so it fits on the same row as the other buttons --}}
        <summary class="btn-outline cursor-pointer list-none px-3.5 sm:px-5" aria-label="{{ __('مشاركة') }}">
            <x-ui-icon name="share" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('مشاركة') }}</span>
        </summary>
    @else
        <summary class="inline-flex cursor-pointer list-none items-center gap-1 py-1.5 text-gray-500 hover:text-ink">
            <x-ui-icon name="share" class="h-4 w-4" /> {{ __('مشاركة') }}
        </summary>
    @endif

    <div class="absolute end-0 z-30 mt-2 w-56 rounded-2xl border border-gray-200 bg-white p-1.5 text-sm text-ink shadow-xl">
        <button type="button" data-native-share data-url="{{ $url }}" data-text="{{ $text }}"
                class="hidden w-full items-center gap-3 rounded-xl px-3 py-2 text-start hover:bg-gray-50">
            <x-ui-icon name="share" class="h-5 w-5 text-gray-600" /> {{ __('مشاركة عبر…') }}
        </button>
        @foreach ($networks as [$icon, $label, $href, $color])
            <a href="{{ $href }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
                <x-ui-icon :name="$icon" class="h-5 w-5 {{ $color }}" /> {{ $label }}
            </a>
        @endforeach
        <button type="button" data-copy-url="{{ $url }}" data-copied-label="{{ __('تم نسخ الرابط ✓') }}"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-start hover:bg-gray-50">
            <x-ui-icon name="link" class="h-5 w-5 text-gray-600" /> <span>{{ __('نسخ الرابط') }}</span>
        </button>
    </div>
</details>
